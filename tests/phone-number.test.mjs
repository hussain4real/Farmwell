import assert from 'node:assert/strict';
import test from 'node:test';
import { internationalPhoneNumber } from '../resources/js/lib/phoneNumber.ts';

test('combines the locked calling code with a national or pasted number', () => {
    assert.equal(internationalPhoneNumber('+974', '5555 0101'), '+97455550101');
    assert.equal(internationalPhoneNumber('+234', '0801 234 5678'), '+2348012345678');
    assert.equal(internationalPhoneNumber('+974', '+974 5555 0101'), '+97455550101');
    assert.equal(internationalPhoneNumber('+39', '06 12345678'), '+390612345678');
    assert.equal(internationalPhoneNumber('+974', ''), '');
});
