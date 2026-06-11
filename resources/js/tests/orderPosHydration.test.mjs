import test from 'node:test';
import assert from 'node:assert/strict';

import {
    findOrderProcessTabIndex,
    hydrateOrderProcessTab,
    resetPosSaleTab,
} from '../utils/orderPosHydration.js';

const order = {
    id: 16,
    code: 'DH-NEW-16',
    customer: { id: 2, name: 'Customer' },
    note: 'Server note',
    totals: {
        discount: 1000,
        deposit_remaining: 2000,
        remaining: 7000,
        suggested_customer_pay_now: 6500,
        order_paid_total: 3000,
    },
    delivery: {
        is_delivery: false,
        delivery_mode: 'none',
    },
    items: [{
        order_item_id: 41,
        product_id: 9,
        sku: 'SKU-9',
        name: 'Product 9',
        qty: 3,
        fulfilled_quantity: 1,
        remaining_quantity: 2,
        price: 5000,
        discount: 0,
        has_serial: false,
        stock_quantity: 10,
    }],
};

test('existing exact order tab is selected and fully rehydrated from server', () => {
    const staleTab = {
        id: 3,
        mode: 'process_order',
        source_order_id: 16,
        source_order_code: 'DH-NEW-16',
        cart: [],
        selectedCustomer: null,
        customerPaid: 0,
    };

    assert.equal(findOrderProcessTabIndex([staleTab], order), 0);

    const hydrated = hydrateOrderProcessTab(staleTab, order);
    assert.equal(hydrated.cart.length, 1);
    assert.equal(hydrated.cart[0].quantity, 2);
    assert.equal(hydrated.selectedCustomer.id, 2);
    assert.equal(hydrated.orderDepositAmount, 2000);
    assert.equal(hydrated.customerPaid, 6500);
    assert.equal(hydrated.orderPaymentSummary.order_paid_total, 3000);
});

test('same numeric id with a different order code is treated as stale', () => {
    const staleDatabaseTab = {
        source_order_id: 16,
        source_order_code: 'DH-OLD-DATABASE',
    };

    assert.equal(findOrderProcessTabIndex([staleDatabaseTab], order), -1);
});

test('checkout reset returns a clean normal sale tab', () => {
    const createTab = (type) => ({
        type,
        mode: 'normal',
        source_order_id: null,
        source_order_code: '',
        orderDepositAmount: 0,
        cart: [],
        selectedCustomer: null,
        delivery: { is_delivery: false, delivery_mode: 'none' },
    });

    const reset = resetPosSaleTab(createTab);
    assert.equal(reset.type, 'sale');
    assert.equal(reset.mode, 'normal');
    assert.equal(reset.source_order_id, null);
    assert.equal(reset.source_order_code, '');
    assert.equal(reset.orderDepositAmount, 0);
    assert.deepEqual(reset.cart, []);
});
