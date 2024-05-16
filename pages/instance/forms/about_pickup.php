<?php if ($activity->pickup_date && period($activity->subject_appeir_date, $activity->pickup_date)) { ?>
    <?php //// A REMPLACER PAR L'UN DES BOUTONS DEJA PRESENT DANS LE MENU EQUIPE ET QUI EST ACCESSIBLE SEULEMENT PAR LE PROF ?>
    <form method="post" action="index.php?p=FetchingMenu&amp;a=<?=urlencode($activity->codename); ?>">
	<input
	    type="submit"
		  class="instance_button"
		  value="<?=$Dictionnary["DeliverWork"]; ?>"
	/>
    </form>
<?php } ?>
