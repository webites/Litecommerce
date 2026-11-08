<?php

return [
    'unknown_order' => 'Nieznane zamówienie [:order_id].',
    'order_not_paid' => 'Zamówienie nie zostało jeszcze opłacone — zwrot nie ma czego dotyczyć. Użyj anulowania zamówienia.',
    'no_lines' => 'Zgłoszenie zwrotu musi zawierać co najmniej jedną pozycję.',
    'line_not_in_order' => 'Pozycja zamówienia [:line_id] nie należy do tego zamówienia.',
    'quantity_must_be_positive' => 'Ilość do zwrotu dla pozycji [:line_id] musi być dodatnia.',
    'quantity_exceeds_remaining' => 'Ilość do zwrotu dla pozycji [:line_id] przekracza pozostałą do zwrotu ilość (:remaining).',
    'order_line_not_resolvable' => 'Pozycja zamówienia [:line_id] nie jest już dostępna.',
    'approved_quantity_exceeds_ordered' => 'Zatwierdzona ilość dla pozycji [:line_id] przekroczyłaby zamówioną ilość.',
    'restocked_quantity_exceeds_returned' => 'Ilość zwracana na stan dla pozycji [:line_id] nie może przekraczać zwracanej ilości.',
    'no_delivery_to_refund' => 'To zamówienie nie ma pozycji dostawy do zwrotu.',
    'delivery_refund_exceeds_remaining' => 'Kwota zwrotu dostawy przekracza pozostałą do zwrotu wartość (:remaining).',
];
