import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import { formatCurrency } from '../resources/js/lib/currency.ts';

test('formats amounts using their own currency without losing decimal precision', () => {
    assert.equal(formatCurrency('1234567.80', 'NGN'), '₦1,234,567.80 NGN');
    assert.equal(formatCurrency('-25.00', 'USD'), '-$25.00 USD');
    assert.equal(formatCurrency('42.50', 'QAR'), 'QAR 42.50');
    assert.equal(formatCurrency(null, 'NGN'), '—');
    assert.equal(formatCurrency('9007199254740993.99', 'NGN'), '₦9,007,199,254,740,993.99 NGN');
});
