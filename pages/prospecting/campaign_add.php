<section class="campaign_add">
    <header class="campaign_panel_header">
        <h2>Ajouter une campagne</h2>
        <div class="campaign_panel_actions">
            <input
                class="campaign_add_button"
                type="button"
                value="Ajouter"
                onclick="silent_submitf(document.getElementById('campaign_add_form'), {tofill: 'campaign_list', toclear: 'campaign_list', clear_form: true, after_success: prospecting_campaign_tabs_after_update});"
            />
        </div>
    </header>

    <form
        id="campaign_add_form"
        class="campaign_add_form"
        method="post"
        action="/api/campaign"
        onsubmit="return silent_submitf(this, {tofill: 'campaign_list', toclear: 'campaign_list', clear_form: true, after_success: prospecting_campaign_tabs_after_update});"
    >
        <label>
            Nom
            <input type="text" name="name" required />
        </label>
        <label>
            Début
            <input type="date" name="start_date" required />
        </label>
        <label>
            Fin
            <input type="date" name="end_date" required />
        </label>
        <label class="campaign_description">
            Description
            <textarea name="description"></textarea>
        </label>
    </form>
</section>
