# Thème Colint pour Infosphère

Ce dossier contient une surcouche **strictement graphique** pour Infosphère.
Elle ne modifie ni la structure HTML, ni le placement, ni les dimensions des composants.

## Activation normale

Infosphère charge automatiquement `style/<école>/*.css` après les feuilles de style structurelles.
Pour une école dont le codename est `colint`, il suffit donc de conserver ce dossier sous :

    style/colint/

Le fichier `theme.css` sera chargé automatiquement.

## Prévisualisation sur une instance EFRITS

Pour tester le thème sans modifier la configuration ni le code PHP, on peut créer temporairement une copie chargée en dernier dans le dossier de style EFRITS :

    cp style/colint/theme.css style/efrits/zz-colint-preview.css

Puis supprimer le fichier après la démonstration :

    rm style/efrits/zz-colint-preview.css

Le préfixe `zz-` permet à la feuille de passer après les autres fichiers `style/efrits/*.css` dans le chargement par `glob()`.

## Identité visuelle

La V2 conserve la logique noir / blanc / jaune de Colint, mais l'adapte à la densité
d'Infosphère au lieu de transformer chaque bloc en panneau encadré. La structure, les
dimensions, les espacements et la quantité d'information affichée restent inchangés.

Principes :

- fond ivoire clair et surfaces blanches ;
- jaune vif réservé aux actions, sélections et repères ;
- noir pour les titres et les surfaces réellement sombres ;
- hiérarchie plus éditoriale et plus plate ;
- suppression des ombres sèches et des effets de bouton « application de bureau » ;
- séparateurs dessinés majoritairement avec des `box-shadow: inset` plutôt qu'avec
  des bordures qui réduisent la boîte de contenu et peuvent provoquer des scrollbars ;
- pas de règle globale `* { color: ... }`, afin d'éviter le texte noir sur fond noir
  dans les composants historiques ;
- disparition des effets verts / transparents / floutés du thème EFRITS.

Les couleurs principales sont centralisées dans les variables CSS en tête de `theme.css`.

## Logo et favicon

Aucun logo Colint n'est embarqué dans ce patch. Pour une vraie instance client, Infosphère sait déjà utiliser les assets de l'école :

    dres/school/colint/icon.png
    dres/school/colint/favicon.png

On peut donc ajouter les fichiers officiels fournis par Colint sans toucher au thème.
