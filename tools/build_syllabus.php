<?php

/*
** Format du syllabus:
** - EN TETE GENERAL
**   - TRIMESTRE
**     - Matière
**     - Matière
**     - Matière
**   - TRIMESTRE
**     - Matière
**     - Matière
**     - Matière
**   - TRIMESTRE
**     - Matière
**     - Matière
**     - Matière
** Ou:
**   - TRIMESTRE
**     - Matière
**     - Matière
**     - Matière
** Ou:
**     - Matière
**
** EN TETE:
**  Description (manuelle)
**  Blocs de compétences
** TRIMESTRE:
**  Description (manuelle)
**  Tableau récapitulatif
** MATIERE:
**  Nom de code, nom, professeur responsable, credits, volume horaire general (hors soutenance et suivi)
**  Compétences associées, description manuelle, bibliographie
*/

function build_year_syllabus($activity, $cycles, $header)
{

}

function build_cycle_syllabus($activity, $cycles, $header)
{

}

function build_activity_syllabus($activity, $cycles)
{

}

function build_syllabus($activity, $cycles = NULL, $header = NULL)
{
    if (is_array($activity) == false)
	$activity = [$activity];


}
