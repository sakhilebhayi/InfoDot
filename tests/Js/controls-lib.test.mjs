import test from 'node:test';
import assert from 'node:assert/strict';
import { acceptsFile, addDays, addMonths, filterOptions, humanBytes, isoWeekday, monthGrid, parseIso } from '../../resources/js/controls-lib.js';

test('parseIso accepts only real days', () => {
    assert.deepEqual(parseIso('2026-11-25'), { y: 2026, m: 11, d: 25 });
    assert.equal(parseIso('2026-02-30'), null);
    assert.equal(parseIso('2026-13-01'), null);
    assert.equal(parseIso('25/11/2026'), null);
    assert.equal(parseIso(''), null);
    assert.deepEqual(parseIso('2028-02-29'), { y: 2028, m: 2, d: 29 });
    assert.equal(parseIso('2027-02-29'), null);
});

test('day and month arithmetic crosses months, years and leap days', () => {
    assert.equal(addDays('2026-11-30', 1), '2026-12-01');
    assert.equal(addDays('2026-12-31', 1), '2027-01-01');
    assert.equal(addDays('2028-02-28', 1), '2028-02-29');
    assert.equal(addDays('2027-02-28', 1), '2027-03-01');
    assert.equal(addDays('2026-03-01', -1), '2026-02-28');
    assert.equal(addMonths('2026-12-15', 1), '2027-01-01');
    assert.equal(addMonths('2026-01-31', -1), '2025-12-01');
});

test('weekdays are 1 for Monday to 7 for Sunday', () => {
    assert.equal(isoWeekday('2026-11-23'), 1);
    assert.equal(isoWeekday('2026-11-29'), 7);
});

test('a month grid is six weeks starting on the chosen weekday', () => {
    const grid = monthGrid({ year: 2026, month: 11, weekStart: 1, value: '2026-11-25', today: '2026-11-20', min: '2026-11-10', max: '2026-11-28', disabled: ['2026-11-26'] });
    assert.equal(grid.length, 42);
    assert.equal(grid[0].iso, '2026-10-26'); // 1 November 2026 is a Sunday, so the week starts on Monday 26 October
    assert.equal(grid[0].inMonth, false);
    assert.equal(grid.find((d) => d.iso === '2026-11-01').inMonth, true);
    assert.equal(grid.find((d) => d.iso === '2026-11-25').selected, true);
    assert.equal(grid.find((d) => d.iso === '2026-11-20').today, true);
    assert.equal(grid.find((d) => d.iso === '2026-11-09').disabled, true, 'before min');
    assert.equal(grid.find((d) => d.iso === '2026-11-29').disabled, true, 'after max');
    assert.equal(grid.find((d) => d.iso === '2026-11-26').disabled, true, 'in the list');
    assert.equal(grid.find((d) => d.iso === '2026-11-24').disabled, false);

    const sundayFirst = monthGrid({ year: 2026, month: 11, weekStart: 7 });
    assert.equal(sundayFirst[0].iso, '2026-11-01');
});

test('files are checked against an accept list', () => {
    const png = { name: 'logo.PNG', type: 'image/png' };
    const pdf = { name: 'terms.pdf', type: 'application/pdf' };
    assert.equal(acceptsFile(png, 'image/*'), true);
    assert.equal(acceptsFile(pdf, 'image/*'), false);
    assert.equal(acceptsFile(pdf, 'image/*,.pdf'), true);
    assert.equal(acceptsFile(png, '.png'), true);
    assert.equal(acceptsFile(pdf, 'application/pdf'), true);
    assert.equal(acceptsFile(pdf, ''), true);
    assert.equal(acceptsFile({ name: 'x.exe', type: '' }, 'image/*,.pdf'), false);
    assert.equal(acceptsFile({ name: 'photo.HEIC', type: '' }, 'image/*'), true, 'no reported type: the extension decides');
    assert.equal(acceptsFile({ name: 'notes.pdf', type: '' }, 'application/pdf'), true);
    assert.equal(acceptsFile({ name: 'notes', type: '' }, 'image/*'), false);
});

test('bytes are written for people', () => {
    assert.equal(humanBytes(800), '800 B');
    assert.equal(humanBytes(120 * 1024), '120 KB');
    assert.equal(humanBytes(3.2 * 1048576), '3.2 MB');
    assert.equal(humanBytes(25 * 1048576), '25 MB');
});

test('options are filtered by every word, ignoring case and accents', () => {
    const options = [{ label: 'Johannesburg' }, { label: 'Cape Town' }, { label: 'Durban' }, { label: 'São Paulo' }];
    assert.equal(filterOptions(options, '').length, 4);
    assert.deepEqual(filterOptions(options, 'JOH').map((o) => o.label), ['Johannesburg']);
    assert.deepEqual(filterOptions(options, 'cape tow').map((o) => o.label), ['Cape Town']);
    assert.deepEqual(filterOptions(options, 'sao').map((o) => o.label), ['São Paulo']);
    assert.equal(filterOptions(options, 'zzz').length, 0);
});
