<?php

$FamousQuote["fr"] = [
    "La même eau bouillante qui ramollit la pomme de terre durcit l'oeuf",
    "Quand on tombe dans l'eau, la pluie ne fait plus peur",
    "'Un de ces jours' signifie aucune de ces jours",
    "Celui qui n'a pas bâti de maison croit que les murs sortent de terre",
    "Les bijoux sont la dernière chose qu'on achète et la première qu'on vend",
    "Ceux qui souffrent de la même maladie compatissent entre eux, ceux qui ont les mêmes soucis s'entraident",
    "Qui est seul n'est pas toujours pauvre, mais qui est pauvre est trop souvent seul",
    "La richesse donne de la beauté aux laids, des pieds aux boiteux, des yeux aux aveugles, de l'intérêt aux larmes",
    "Nous ne connaissons la valeur de l'eau que lorsque le puit est à sec",
    "Ce n'est pas le jour où tu es bien habillé que tu rencontres ta belle-mère",
    "Quand on est dans l'imitation, on ne peut être qu'en retard",
    "Du pain en temps de paix est meilleur que du gâteau en temps de guerre",
    "Assis sur les genoux d'une mère pauvre aimante, tout enfant est riche",
    "Le pessimisme face au futur ne doit pas être un argument supplémentaire pour l'inaction dans le présent",
    "Le peuple regorge de talents bloqués derrière le mur d'argent",
    "Là où il y a une volonté, il y a un chemin",
    "De tous les événements inattendus, le plus inattendu c'est la vieillesse",
    "La réalité ne pardonne pas une seule erreur à la théorie",
    "Il faut avoir une parfaite conscience de ses propres limites, surtout si on veut les élargir",
    "Le pessimisme de la connaissance n'empêche pas l'optimisme de la volonté",
    "La beauté trop formelle devient une grimace",
    "La liberté est toujours la liberté des dissidents",
    "Ceux qui ne bougent pas ne remarquent pas leurs chaînes",
    "D'une façon générale, on ne doit pas oublier d'être bon, car la bonté, dans les relations avec les hommes, fait bien plus que la sévérité",
    "Avant qu'une révolution arrive, elle est perçue comme impossible; après cela, elle est considéré comme inévitable",
    "Nous serons victorieux si nous n'avons pas oublié comment apprendre",
    "Le temps de vivre, c'est aussi le temps d'aimer",
    "La science consiste à faire ce qu'on fait en sachant et en disant que c'est tout ce qu'on peut faire, en énonçant les limites de la validité de ce qu'on fait",
    "Proverbe vegan: On ne fait pas d'omelette",
    "Le courage n'est pas l'absence de peur, mais la capacité de la vaincre",
    "Choisissez un travail que vous aimez et vous n'aurez pas à travailler un seul jour de votre vie",
    "Le domaine de la liberté commence là où s'arrête le travail déterminé par la nécéssité",
    "Offrir l'amitié à qui veut l'amour, c'est donner du pain à qui meurt de soif",
    "Celui qui sème l'injustice moissonne le malheur",
    "Tout n'est pas politique, mais la politique s'intéresse à tout",
    "Tous les ennemis de la liberté parlent contre le despotisme d'opinion, parce qu'ils préfèrent le despotisme de la force",
    "Après la faculté de penser, celle de communiquer ses pensées à ses semblables est l'attribut le plus frappant qui distingue l'homme de la brute",
    "Rien n'est juste que ce qui est honnête; rien n'est utile que ce qui est juste",
    "IL est plus facile de nous ôter la vie que de triompher de nos principes",
    "La clémence qui compose avec la tyrannie est barbare",
    "Après le pain, l'éducation est le premier besoin du peuple",
    "Pour enchaîner les peuples, on commence par les endormir",
    "La plupart des gens échouent car ils ont peur de se dresser contre leurs proches.",
    "Vous ne pouvez réussir qu'en acceptant les responsabilités de vos actions.",
    "Ne jugez pas quelqu'un sur les ragots des autres",
];

$FamousQuote["en"] = [

];

function famous_quote($id = -1)
{
    global $FamousQuote;
    global $Language;

    if (!isset($FamousQuote[$Language]))
	return ("");
    $fq = $FamousQuote[$Language];
    if ($id != -1)
	$fq = $fq[$id % count($fq)];
    else
	$fq = $fq[rand(0, count($fq) - 1)];
    return ($fq);
}
