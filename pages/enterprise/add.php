<h2><?=htmlspecialchars($Dictionnary["AddEnterprise"] ?? "Ajouter une organisation"); ?></h2>
<p class="enterprise_add_notice">
    Crée ici une organisation / société. Une école pourra ensuite s'y rattacher par codename depuis la page École.
</p>
<form onsubmit="return false;" method="POST" action="/api/enterprise">
    <input type="text" name="codename" placeholder="<?=$Dictionnary["CodeName"]; ?>" required /><br />
    <input type="text" name="name" placeholder="Nom courant" required /><br />
    <input type="text" name="legal_name" placeholder="<?=$Dictionnary["LegalName"]; ?>" /><br />
    <input type="text" name="siret" placeholder="SIRET" /><br />
    <input type="text" name="registration_registry" placeholder="Registre d'immatriculation, ex : RCS de Créteil" /><br />
    <input type="text" name="registration_number" placeholder="Numéro d'immatriculation, ex : 909 292 773" /><br />
    <input type="text" name="share_capital" placeholder="Capital social, ex : 1 000 €" /><br />
    <input type="text" name="phone" placeholder="<?=$Dictionnary["Phone"]; ?>" /><br />
    <input type="mail" name="mail" placeholder="<?=$Dictionnary["Mail"]; ?>" /><br />
    <input type="text" name="website" placeholder="Site web" /><br />

    <h3>Siège social</h3>
    <input type="text" name="head_office_address_line1" placeholder="Adresse — ligne 1" /><br />
    <input type="text" name="head_office_address_line2" placeholder="Adresse — ligne 2" /><br />
    <input type="text" name="head_office_zipcode" placeholder="Code postal" /><br />
    <input type="text" name="head_office_city" placeholder="Ville" /><br />
    <input type="text" name="head_office_country" placeholder="Pays" value="France" /><br />

    <textarea name="activity" placeholder="Activité / service d'accueil"></textarea><br />
    <textarea name="billing_information" placeholder="Informations de paiement / RIB (IBAN, BIC, modalités)"></textarea><br />
    <textarea name="notes" placeholder="Notes internes"></textarea><br />

    <p class="enterprise_add_notice">
        Les logos entreprise sont optionnels. Les logos nécessaires aux documents de l'école restent portés par l'école.
    </p>
    <label>Logo site entreprise optionnel</label>
    <input type="file" name="icon" accept="image/png,image/jpeg" /><br />
    <label>Logo document entreprise optionnel</label>
    <input type="file" name="document_logo" accept="image/png,image/jpeg" /><br />
    <input
        type="button"
        onclick="silent_submitf(this.parentNode, {tofill: 'enterprise_list', toclear: 'enterprise_list', wrapper: 'enterprises', clear_form: true});"
        value="<?=htmlspecialchars($Dictionnary["AddEnterprise"] ?? "Ajouter l'organisation"); ?>"
    />
</form>
