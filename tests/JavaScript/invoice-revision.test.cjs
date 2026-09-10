const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

test('revision script registers handlers and opens the invoice modal without CDN dependencies', () => {
    const elements = {};
    const config = {
        orderId: 1, details: [],
        orderItems: [{ id: 10, medicine_name: "Obat 'A'", price: 100 }, { id: 20, medicine_name: 'Obat B' }],
        items: [{ details_id: 1, order_items_id: 10 }, { details_id: 2, order_items_id: 20 }],
    };
    function element(id) {
        return elements[id] ||= {
            textContent: id === 'revision-page-data' ? JSON.stringify(config) : '',
            value: '', options: [],
            classList: { values: new Set(['hidden']), add(v) { this.values.add(v); }, remove(v) { this.values.delete(v); } },
            add(option) { this.options.push(option); },
        };
    }
    const context = {
        document: { getElementById: element, addEventListener() {} },
        Option: function (text, value) { this.text = text; this.value = value; this.dataset = {}; },
    };
    context.window = context;
    vm.createContext(context);
    // A variable from another page script must not prevent the revision script from loading.
    vm.runInContext('const ORDER_ID = 999;', context);
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../../public/js/invoice-revision.js'), 'utf8'), context);
    const view = fs.readFileSync(path.join(__dirname, '../../resources/views/orders/revision.blade.php'), 'utf8');
    for (const match of view.matchAll(/on(?:click|change)="(\w+)\(/g)) {
        assert.equal(typeof context[match[1]], 'function', match[1]);
    }
    context.openAddModal('1', "NT'1");
    assert.equal(element('add_target_code').innerText, "NT'1");
    assert.equal(element('addModal').classList.values.has('hidden'), false);
    assert.deepEqual(element('add_order_items_id').options.map(o => o.value), ['', 10]);
    context.closeAddModal();
    assert.equal(element('addModal').classList.values.has('hidden'), true);
    context.openAddModal('2', 'NT2');
    assert.deepEqual(element('add_order_items_id').options.map(o => o.value), ['', 20]);
});
