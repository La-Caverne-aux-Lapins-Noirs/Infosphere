-- Keep the number of a deleted issued document for history while freeing the
-- active invoice_reference unique key, so that the number can be reused.
ALTER TABLE billing_entry
  ADD COLUMN IF NOT EXISTS deleted_invoice_reference varchar(128) DEFAULT NULL AFTER invoice_reference;

UPDATE billing_entry
SET deleted_invoice_reference = invoice_reference,
    invoice_reference = NULL
WHERE deleted IS NOT NULL
  AND invoice_reference IS NOT NULL
  AND invoice_reference != '';

-- The original migration used INFSPH while the billing UI and intended numbering
-- use INF-0001. Preserve every custom prefix; only replace the untouched default.
INSERT INTO configuration (codename, value)
SELECT 'billing_invoice_prefix', 'INF'
WHERE NOT EXISTS (
  SELECT 1 FROM configuration WHERE codename = 'billing_invoice_prefix'
);

UPDATE configuration
SET value = 'INF'
WHERE codename = 'billing_invoice_prefix'
  AND value = 'INFSPH';
