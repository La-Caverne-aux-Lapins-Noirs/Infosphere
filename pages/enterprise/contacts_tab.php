<script>
function enterprise_contact_existing_changed(source)
{
    let form = source && source.closest ? source.closest("form") : null;
    let fields = form ? form.querySelector("[data-enterprise-new-contact-fields]") : null;
    let existing = !!(source && source.value && source.value.trim());

    if (!fields)
        return;
    fields.classList.toggle("enterprise_contact_new_fields_hidden", existing);
    fields.querySelectorAll("input").forEach(function(input)
    {
        input.disabled = existing;
    });
}
</script>

<div id="enterprise_contacts_dynamic_<?=(int)$enterprise["id"]; ?>">
    <?php require (__DIR__."/contacts_content.php"); ?>
</div>
