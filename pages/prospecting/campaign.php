<?php require_once ("campaign_tools.php"); ?>

<h1 style="display: inline-block; width: auto; margin-right: 50px;">Campagnes de prospection</h1>

<style><?php require ("style.css"); ?></style>
<script><?php require ("script.js"); ?></script>

<input
    type="button"
    onclick="document.location='?p=ProspectingMenu&amp;pp=<?=$Position; ?>'"
    value="Retour aux prospects"
    style="width: 300px; background-color: #00FF00; color: black; font-weight: bold;"
/>

<section class="campaign_add">
    <h2>Ajouter une campagne</h2>
    <form method="post" action="/api/campaign" onsubmit="return silent_submitf(this, {tofill: 'campaign_list', toclear: 'campaign_list', clear_form: true});">
        <input type="text" name="name" placeholder="Nom" required />
        <input type="date" name="start_date" required />
        <input type="date" name="end_date" required />
        <textarea name="description" placeholder="Description"></textarea>
        <input
            type="button"
            value="Ajouter"
            onclick="silent_submitf(this, {tofill: 'campaign_list', toclear: 'campaign_list', clear_form: true});"
        />
    </form>
</section>

<div id="campaign_list">
    <?php require ("campaign_list.php"); ?>
</div>
