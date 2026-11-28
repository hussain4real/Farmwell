#!/usr/bin/env bash
set -euo pipefail

[[ "$GITHUB_REPOSITORY" == 'hussain4real/Farmwell' && "$GITHUB_EVENT_NAME" == 'push' ]]
[[ "$GITHUB_SHA" =~ ^[0-9a-f]{40}$ && "$(git rev-parse HEAD)" == "$GITHUB_SHA" ]]

case "$GITHUB_REF" in
    refs/heads/stage)
        worker=farmwell-staging
        config=wrangler.jsonc
        host=staging.farmwell.app
        : "${FARMWELL_ACCESS_CLIENT_ID:?Staging health check needs an Access service token client ID}"
        : "${FARMWELL_ACCESS_CLIENT_SECRET:?Staging health check needs an Access service token client secret}"
        ;;
    refs/heads/main)
        worker=farmwell-production
        config=wrangler.production.jsonc
        host=app.farmwell.app
        ;;
    *) echo 'Ref is not an approved deployment branch' >&2; exit 1 ;;
esac
: "${CLOUDFLARE_API_TOKEN:?Configure the deployment environment Cloudflare API token}"

account=40344948b73fa9da3008958fdc3faf12
export CLOUDFLARE_ACCOUNT_ID="$account"
jq -e --arg worker "$worker" --arg host "$host" --arg account "$account" \
    '.name == $worker and .vars.CLOUDFLARE_ACCOUNT_ID == $account and
     .vars.APP_URL == ("https://" + $host) and
     any(.routes[]; .pattern == $host and .custom_domain == true)' "$config" >/dev/null
docker info >/dev/null

health() {
    local code
    code=$(
        if [[ "$worker" == 'farmwell-staging' ]]; then
            printf 'header = "CF-Access-Client-Id: %s"\nheader = "CF-Access-Client-Secret: %s"\n' \
                "$FARMWELL_ACCESS_CLIENT_ID" "$FARMWELL_ACCESS_CLIENT_SECRET"
        fi |
        curl --config - --silent --show-error --max-time 35 --output /dev/null \
            --header 'Cache-Control: no-cache' --write-out '%{http_code}' \
            "https://$host/up?release=$GITHUB_SHA&attempt=$1"
    ) || return 1
    if [[ "$code" != '200' ]]; then
        echo "Farmwell health check returned HTTP $code" >&2
        return 1
    fi
}

api() {
    printf 'header = "Authorization: Bearer %s"\n' "$CLOUDFLARE_API_TOKEN" |
        curl --config - --fail --silent --show-error --max-time 25 \
            "https://api.cloudflare.com/client/v4/accounts/$account/workers/scripts/$worker/$1"
}

active_version() {
    api deployments | jq -er '.result.deployments[0].versions |
        select(length == 1 and .[0].percentage == 100) | .[0].version_id'
}

branch=${GITHUB_REF#refs/heads/}
tip() {
    gh api "repos/$GITHUB_REPOSITORY/git/ref/heads/$branch" --jq '.object.sha'
}

current=$(tip)
if [[ "$current" != "$GITHUB_SHA" ]]; then
    echo 'A newer commit is on the branch. Skipping this release.' >> "$GITHUB_STEP_SUMMARY"
    exit 0
fi

previous=$(active_version)
health before
{
    printf 'Target: \x60%s\x60\n\nCommit: \x60%s\x60\n\nPrevious Worker version: \x60%s\x60\n\n' \
        "$worker" "$GITHUB_SHA" "$previous"
    echo 'For rollback, redeploy the last known-good commit with the same target config so the Worker and container images are both restored.'
} >> "$GITHUB_STEP_SUMMARY"

current=$(tip)
if [[ "$current" != "$GITHUB_SHA" ]]; then
    echo 'A newer commit arrived during preflight. Skipping this release.' >> "$GITHUB_STEP_SUMMARY"
    exit 0
fi

tag=git-$GITHUB_SHA
"$RUNNER_TEMP/farmwell-wrangler/node_modules/.bin/wrangler" deploy \
    --config "$config" --keep-vars --tag "$tag" --message "Farmwell $branch $GITHUB_SHA"

for attempt in {1..8}; do
    if deployed=$(active_version) \
        && api "versions/$deployed" | jq -e --arg tag "$tag" '.result.annotations["workers/tag"] == $tag' >/dev/null \
        && health "$attempt"; then
        printf '\nActive Worker version: \x60%s\x60. App /up returned HTTP 200.\n' "$deployed" >> "$GITHUB_STEP_SUMMARY"
        exit 0
    fi
    if (( attempt < 8 )); then sleep 15; fi
done
echo 'Unable to verify the tagged deployment and app health; inspect Cloudflare before retrying.' >&2
exit 1
