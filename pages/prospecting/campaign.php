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

<div id="campaign_list">
    <?php require ("campaign_list.php"); ?>
</div>
