<?php

return [
    'unknown_order' => 'Unknown order [:order_id].',
    'order_not_paid' => 'This order has not been paid yet — there is nothing to return. Use order cancellation instead.',
    'no_lines' => 'The return request must contain at least one line.',
    'line_not_in_order' => 'Order line [:line_id] does not belong to this order.',
    'quantity_must_be_positive' => 'The return quantity for line [:line_id] must be positive.',
    'quantity_exceeds_remaining' => 'The return quantity for line [:line_id] exceeds the remaining returnable quantity (:remaining).',
    'order_line_not_resolvable' => 'Order line [:line_id] no longer resolvable.',
    'approved_quantity_exceeds_ordered' => 'The approved quantity for line [:line_id] would exceed the ordered quantity.',
    'restocked_quantity_exceeds_returned' => 'The restocked quantity for line [:line_id] cannot exceed the returned quantity.',
    'no_delivery_to_refund' => 'This order has no delivery line to refund.',
    'delivery_refund_exceeds_remaining' => 'The delivery refund amount exceeds the remaining refundable value (:remaining).',
];
