const assert = require('node:assert/strict');
const {spell, parseWords, parseAmount} = require('../../public/js/kwitansi.js');
for (let n = 0; n < 10000; n++) assert.equal(parseWords(spell(n) + ' rupiah'), n);
for (const n of [12000, 1000000, 1001001, 12012012, 999999999999]) assert.equal(parseWords(spell(n)), n);
assert.equal(parseAmount('1.250.000,00'), 1250000);
for (const value of ['-1', '1,50', 'abc', '1.23', '1000000000000']) assert.equal(parseAmount(value), null);
for (const value of ['satu satu', 'dua belas belas', 'seribu ribu', 'foo', '']) assert.equal(parseWords(value), null);
console.log('Kwitansi: 10,005 round trips and invalid inputs passed.');
