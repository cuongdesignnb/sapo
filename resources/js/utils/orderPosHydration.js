export const findOrderProcessTabIndex = (tabs, order) => {
    if (!Array.isArray(tabs) || !order) return -1;

    return tabs.findIndex((tab) => (
        Number(tab?.source_order_id) === Number(order.id)
        && String(tab?.source_order_code || '') === String(order.code || '')
    ));
};

export const mapOrderItemsToPosCart = (items = []) => items.map((item) => ({
    product: {
        id: item.product_id,
        sku: item.sku,
        name: item.name,
        cost_price: item.cost_price || 0,
        retail_price: item.price,
        has_serial: item.has_serial,
        stock_quantity: item.stock_quantity,
    },
    quantity: item.remaining_quantity,
    targetQuantity: item.remaining_quantity,
    orderedQuantity: item.qty,
    fulfilledQuantity: item.fulfilled_quantity,
    orderItemId: item.order_item_id,
    price: item.price,
    discount: item.discount,
    is_serial_product: item.has_serial,
    serials: [],
    serialInput: '',
    showSerialDropdown: false,
    allAvailableSerials: [],
    availableSerials: [],
    serialLoading: false,
}));

export const hydrateOrderProcessTab = (tab, order) => ({
    ...tab,
    mode: 'process_order',
    source_order_id: order.id,
    source_order_code: order.code,
    selectedCustomer: order.customer,
    discount: order.totals.discount,
    orderDepositAmount: order.totals.deposit_remaining,
    orderPaymentSummary: { ...order.totals },
    customerPaid: Math.max(0, Number(order.totals.suggested_customer_pay_now ?? order.totals.remaining) || 0),
    note: order.note,
    saleMode: order.delivery.is_delivery ? 'delivery' : 'normal',
    delivery: {
        is_delivery: order.delivery.is_delivery,
        delivery_mode: order.delivery.delivery_mode || (order.delivery.is_delivery ? 'self' : 'none'),
        delivery_partner: order.delivery.delivery_partner || '',
        tracking_code: order.delivery.tracking_code || '',
        delivery_fee: order.delivery.delivery_fee || 0,
        cod_amount: order.delivery.cod_amount || 0,
        receiver_name: order.delivery.receiver_name || order.customer?.name || '',
        receiver_phone: order.delivery.receiver_phone || order.customer?.phone || '',
        receiver_address: order.delivery.receiver_address || order.customer?.address || '',
        receiver_ward: order.delivery.receiver_ward || '',
        receiver_district: order.delivery.receiver_district || '',
        receiver_city: order.delivery.receiver_city || '',
        weight: order.delivery.weight || 0,
        delivery_note: order.delivery.delivery_note || '',
    },
    cart: mapOrderItemsToPosCart(order.items),
});

export const resetPosSaleTab = (createTab) => createTab('sale');
