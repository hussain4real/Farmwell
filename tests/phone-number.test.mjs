import assert from 'node:assert/strict';
import test from 'node:test';
import { internationalPhoneNumber, nationalNumberLength, pastedNationalDigits } from '../resources/js/lib/phoneNumber.ts';

test('paste fits the selected country without silently truncating another number', () => {
    assert.equal(nationalNumberLength('+974'), 8);
    assert.equal(nationalNumberLength('+234'), 10);
    assert.equal(pastedNationalDigits('+974', '+974 5555-0101'), '55550101');
    assert.equal(pastedNationalDigits('+234', '0801 234 5678'), '8012345678');
    assert.equal(pastedNationalDigits('+234', '+234 801 234 5678'), '8012345678');
    for (const value of ['+2348012345678', '555501019', '555x0101']) {
        assert.equal(pastedNationalDigits('+974', value), '');
    }
});

test('combines the locked calling code with a national or pasted number', () => {
    assert.equal(internationalPhoneNumber('+974', '5555 0101'), '+97455550101');
    assert.equal(internationalPhoneNumber('+234', '0801 234 5678'), '+2348012345678');
    assert.equal(internationalPhoneNumber('+974', '+974 5555 0101'), '+97455550101');
    assert.equal(internationalPhoneNumber('+39', '06 12345678'), '+390612345678');
    assert.equal(internationalPhoneNumber('+974', ''), '');
});
