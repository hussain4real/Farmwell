<?php

use Illuminate\Mail\Transport\CloudflareTransport;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

test('cloudflare mail transport sends transactional mail through the configured account', function () {
    $response = Mockery::mock(ResponseInterface::class);
    $response->shouldReceive('toArray')->once()->with(false)->andReturn(['success' => true]);
    $response->shouldReceive('getStatusCode')->once()->andReturn(200);
    $client = Mockery::mock(HttpClientInterface::class);
    $client->shouldReceive('request')->once()->withArgs(function (string $method, string $url, array $options): bool {
        return $method === 'POST'
            && $url === 'https://api.cloudflare.com/client/v4/accounts/test-account/email/sending/send'
            && $options['auth_bearer'] === 'test-token'
            && $options['json']['subject'] === 'Staging verification'
            && $options['json']['to'] === ['recipient@example.test'];
    })->andReturn($response);

    expect(config('mail.mailers.cloudflare.transport'))->toBe('cloudflare');
    $transport = new CloudflareTransport('test-account', 'test-token', $client);
    $transport->send((new Email)->from('noreply@example.test')->to('recipient@example.test')->subject('Staging verification')->text('Test message'));
});

test('the configured cloudflare mailer resolves with the installed HTTP client', function () {
    config([
        'services.cloudflare.account_id' => 'test-account',
        'services.cloudflare.key' => 'test-token',
    ]);

    expect(app('mail.manager')->mailer('cloudflare')->getSymfonyTransport())
        ->toBeInstanceOf(CloudflareTransport::class);
});
