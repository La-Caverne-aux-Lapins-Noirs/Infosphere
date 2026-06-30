INSERT INTO configuration (codename, value)
SELECT 'billing_invoice_prefix', 'INFSPH'
WHERE NOT EXISTS (
  SELECT 1
  FROM configuration
  WHERE codename = 'billing_invoice_prefix'
);

ALTER TABLE billing_entry
  DROP INDEX IF EXISTS invoice_reference;

ALTER TABLE billing_entry
  ADD UNIQUE KEY IF NOT EXISTS billing_entry_invoice_reference_unique (invoice_reference);
