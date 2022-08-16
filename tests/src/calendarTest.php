<?php

require_once ("xtestcase.php");
require_once ("../pages/calendar/get_all_rooms.php");
require_once ("../pages/calendar/get_all_years.php");
require_once ("../pages/calendar/get_first_week_date.php");
require_once ("../pages/manage_instance.php");
require_once ("../pages/manage_session.php");

class PlanningTest extends XTestCase
{
    public function testPlanning()
    {
	global $Database;
	global $Dictionnary;
	global $User;
	global $Days;

	clear_table("user");
	clear_table("room");
	clear_table("school_year");
	clear_table("instance");
	clear_table("session");
	clear_table("activity");
	require ("../pages/calendar/constant.php");

	$this->assertSame(count($Days), 7);
	$Database->query("INSERT INTO room (codename, fr_name) VALUES ('no_room', 'Pas de salle')");
	$Database->query("INSERT INTO room (codename, fr_name) VALUES ('salle', 'la salle')");
	$ret = get_all_rooms();
	$this->assertSame($ret["no_room"], $Dictionnary["NoRoom"]);
	$this->assertSame($ret["salle"], "la salle");

	$Database->query("
            INSERT INTO school_year (codename, first_day, cycle)
            VALUES ('cycle0', '2020-01-01 00:00:01', 0)
	");
	$Database->query("
            INSERT INTO school_year (codename, first_day, cycle)
            VALUES ('cycle1', '2020-01-01 00:00:00', 0)
	");
	$Database->query("
            INSERT INTO school_year (codename, first_day, cycle)
            VALUES ('cycle2', '2020-01-01 00:00:00', 0)
	");
	$ret = get_all_years();
	$this->assertSame($ret["cycle0"], 1);
	$this->assertSame($ret["cycle1"], 2);
	$this->assertSame($ret["cycle2"], 3);

	$User = NULL;
	$ret = get_first_week_date();
	$this->assertTrue(abs(strtotime($ret) - time()) < 2);

	$this->AddRealuser();
	$Database->query("INSERT INTO user_school_year (id_user, id_school_year) VALUES (1, 1)");
	$Database->query("INSERT INTO user_school_year (id_user, id_school_year) VALUES (1, 2)");
	unset($User["cycle"]);
	get_user_promotions($User);
	$ret = get_first_week_date();
	$this->assertSame($ret, "2020-01-01 00:00:00");

	$Database->query("UPDATE school_year SET done = 1 WHERE id = 2");
	unset($User["cycle"]);
	get_user_promotions($User);
	$ret = get_first_week_date();
	$this->assertSame($ret, "2020-01-01 00:00:01");

	$Database->query("UPDATE school_year SET done = 1 WHERE id = 1");
	unset($User["cycle"]);
	get_user_promotions($User);
	$ret = get_first_week_date();
	$this->assertTrue(abs(strtotime($ret) - time()) < 2);


	$Database->query("
          INSERT INTO activity (type, codename, fr_name,
             begin_date, subject_appeir_date, pickup_date, end_date
          )
          VALUES (1, 'tp', 'TP',
             '1900-01-01', '1900-01-02', '1900-01-03', '1900-01-04'
          )
	");
	$ret = _resolve_instance("activity", "lel", "cycle1", "");
	$this->assertSame($ret->label, "BadCodeName");
	$this->assertSame($ret->details, "lel");
	$ret = _resolve_instance("activity", "tp", "cycle42", "");
	$this->assertSame($ret->label, "BadCodeName");
	$this->assertSame($ret->details, "cycle42");
	$ret = _resolve_instance("activity", "tp", "cycle1", "a;!;c");
	$this->assertSame($ret->label, "InvalidParameter");
	$this->assertSame($ret->details, "!");
	$ret = add_instance("tp", "cycle42", NULL, "a;b;c");
	$this->assertSame($ret->label, "BadCodeName");
	$this->assertSame($ret->details, "cycle42");

	$ret = add_instance("tp", "cycle1", NULL, "a;b;c");
	$this->assertSame($ret->is_error(), false);
	$this->assertSame($ret->value["id"], 1);

	$ret = db_select_one("* FROM instance");
	$instance = $ret["codename"];
	$this->assertSame($ret["id"], 1);
	$this->assertSame($ret["id_activity"], 1);
	$this->assertSame(preg_match('/^X[0-9a-f]{16}/', $ret["codename"]), 1);
	$this->assertSame(date_to_timestamp($ret["begin_date"]),
			  date_to_timestamp("2020-01-01 00:00:00"));
	$this->assertSame(date_to_timestamp($ret["subject_appeir_date"]),
			  date_to_timestamp("2020-01-02 00:00:00"));
	$this->assertSame(date_to_timestamp($ret["pickup_date"]),
			  date_to_timestamp("2020-01-03 00:00:00"));
	$this->assertSame(date_to_timestamp($ret["end_date"]),
			  date_to_timestamp("2020-01-04 00:00:00"));
	$ret = db_select_one("* FROM instance_school_year");
	$this->assertSame($ret["tags"], "a;b;c");

	$ret = add_cycle_to_instance($instance, "cycle0", "b;c;d");
	$this->assertSame($ret->is_error(), false);
	$ret = db_select_all("* FROM instance_school_year");
	$this->assertSame(count($ret), 2);
	$this->assertSame($ret[0]["id_instance"], 1);
	$this->assertSame($ret[0]["id_school_year"], 2);
	$this->assertSame($ret[0]["tags"], "a;b;c");
	$this->assertSame($ret[1]["id_instance"], 1);
	$this->assertSame($ret[1]["id_school_year"], 1);
	$this->assertSame($ret[1]["tags"], "b;c;d");

	$ret = update_cycle_of_instance($instance, "cycle1", "z");
	$this->assertSame($ret->is_error(), false);
	$ret = db_select_all("* FROM instance_school_year WHERE id = 1");
	$this->assertSame($ret[0]["tags"], "z");

	$ret = remove_cycle_from_instance($instance, "cycle0");
	$this->assertSame($ret->is_error(), false);
	$ret = db_select_all("* FROM instance_school_year");
	$this->assertSame(count($ret), 1);
	$this->assertSame($ret[0]["id_school_year"], 2);

	$ret = add_instance("tp", "cycle0;cycle1");
	$this->assertSame($ret->label, "CannotDeduceStartDate");

	$ret = add_instance("tp", "cycle0;cycle1", "lel");
	$this->assertSame($ret->label, "InvalidParameter");
	$this->assertSame($ret->details, "lel");

	$ret = add_instance("tp", "cycle0;cycle1", "2020-01-01 00:00:00", "a;b;!");
	$this->assertSame($ret->label, "InvalidParameter");
	$this->assertSame($ret->details, "!");

	$ret = add_instance("tp", "cycle0;cycle1", "2020-01-01 00:00:00");
	$this->assertSame($ret->is_error(), false);
	$ret = db_select_all("* FROM instance_school_year WHERE id_instance = 2");
	$this->assertSame(count($ret), 2);
	$this->assertSame($ret[0]["id_school_year"], 1);
	$this->assertSame($ret[1]["id_school_year"], 2);

	$ret = delete_instance("!");
	$this->assertSame($ret->label, "InvalidParameter");
	$this->assertSame($ret->details, "!");

	$ret = delete_instance($instance);
	$this->assertSame($ret->is_error(), false);
	$ret = db_select_all("* FROM instance WHERE deleted = 0");
	$this->assertSame(count($ret), 1);
	$instance = $ret[0];
	$ret = update_cycle_of_instance($instance["codename"], "cycle1", "a;b");
	$ret = db_select_all("* FROM instance_school_year WHERE id_instance = 2");
	$this->assertSame($ret[0]["id_school_year"], 1);
	$this->assertSame($ret[0]["id_instance"], 2);
	$this->assertSame($ret[1]["id_school_year"], 2);
	$this->assertSame($ret[1]["id_instance"], 2);

	$ret = remove_cycle_from_instance($instance["codename"], "cycle0");
	$this->assertSame($ret->is_error(), false);
	$ret = db_select_all("* FROM instance_school_year WHERE id_instance = 2");
	$this->assertSame(count($ret), 1);
	$this->assertSame($ret[0]["id_school_year"], 2);
	$this->assertSame($ret[0]["id_instance"], 2);
	$this->assertSame($ret[0]["tags"], "a;b");

	// Création de deux salles afin tester la création de session
	$lng = ["fr_name" => "Salon"];
	$ret = add_room("salon", 0, "./res/room.png", "./res/room.dab", $lng);
	$this->assertSame($ret->is_error(), false);
	$lng = ["fr_name" => "Cuisine"];
	$ret = add_room("cuisine", 0, "./res/room.png", "./res/room.dab", $lng);
	$this->assertSame($ret->is_error(), false);

	// On verifie la récupération sur erreur - le jour est trop tôt
	$ret = add_session($instance["codename"], "cycle2", ["salon", "cuisine"], "2019-12-31", "14", "16", "d");
	$this->assertSame($ret->label, "InvalidParameter");
	$this->assertSame($ret->details, "2019-12-31");
	$ret = add_session($instance["codename"], "cycle2", ["salon", "cuisine"], "2020-01-02", "6", "16", "d");
	$this->assertSame($ret->label, "InvalidParameter");
	$this->assertSame($ret->details, "6");
	$ret = add_session($instance["codename"], "cycle2", ["salon", "cuisine"], "2020-01-02", "25", "16", "d");
	$this->assertSame($ret->label, "InvalidParameter");
	$this->assertSame($ret->details, "25");
	$ret = add_session($instance["codename"], "cycle2", ["salon", "cuisine"], "2020-01-02", "14", "6", "d");
	$this->assertSame($ret->label, "InvalidParameter");
	$this->assertSame($ret->details, "6");
	$ret = add_session($instance["codename"], "cycle2", ["salon", "cuisine"], "2020-01-02", "14", "25", "d");
	$this->assertSame($ret->label, "InvalidParameter");
	$this->assertSame($ret->details, "25");
	$ret = add_session($instance["codename"], "cycle2", ["salon", "cuisine"], "2020-01-02", "14", "12", "d");
	$this->assertSame($ret->label, "InvalidParameter");
	$this->assertSame($ret->details, "14 - 12");

	// A ce stade, nous avons une instance lié a cycle1, avec comme tag "a;b"
	// Nous créons une session se passant dans 2 salles et dans un cycle supplémentaire
	// et doté d'un tag supplémentaire
	$ret = add_session($instance["codename"], "cycle2", ["salon", "cuisine"], "2020-01-02", "14", "16", "d");
	$this->assertSame($ret->is_error(), false);
	$salon = db_select_one("* FROM room WHERE codename = 'salon'");
	$this->assertSame($salon["id"], 3);
	$cuisine = db_select_one("* FROM room WHERE codename = 'cuisine'");
	$this->assertSame($cuisine["id"], 4);

	$ret = db_select_all("* FROM session");
	$this->assertSame($ret[0]["id"], 1);
	$this->assertSame($ret[0]["id_room"], $salon["id"]);
	$this->assertSame($ret[0]["id_instance"], $instance["id"]);
	$this->assertSame($ret[0]["begin_date"], "2020-01-02 14:00:00");
	$this->assertSame($ret[0]["end_date"], "2020-01-02 16:00:00");

	$this->assertSame($ret[1]["id"], 2);
	$this->assertSame($ret[1]["id_room"], $cuisine["id"]);
	$this->assertSame($ret[1]["id_instance"], $instance["id"]);
	$this->assertSame($ret[1]["begin_date"], "2020-01-02 14:00:00");
	$this->assertSame($ret[1]["end_date"], "2020-01-02 16:00:00");

	// L'instance était liée au "cycle1", soit, le cycle d'id 2
	// donc la session devrait être liée cycle1, cycle2 car cycle2 a été précisé
	// et il y a deux salles, donc 2 sessions, soit au total, 4 liaisons
	// cycle1 dispose des tags de la liaison d'instance_school_year a;b en plus du paramètre (d)
	// cycle2 dispose seulement du tag passé en paramètre "d"
	$ret = db_select_all("* FROM session_school_year ORDER BY id_session ASC, id_school_year ASC");
	$this->assertSame($ret[0]["id_session"], 1);
	$this->assertSame($ret[0]["id_school_year"], 2); // cycle1
	$this->assertSame($ret[0]["tags"], "a;b;d");
	$this->assertSame($ret[1]["id_session"], 1);
	$this->assertSame($ret[1]["id_school_year"], 3); // cycle2
	$this->assertSame($ret[1]["tags"], "d");
	//
	$this->assertSame($ret[2]["id_session"], 2);
	$this->assertSame($ret[2]["id_school_year"], 2); // cycle1
	$this->assertSame($ret[2]["tags"], "a;b;d");
	$this->assertSame($ret[3]["id_session"], 2);
	$this->assertSame($ret[3]["id_school_year"], 3); // cycle2
	$this->assertSame($ret[3]["tags"], "d");

	// On ajoute cycle0 à la session avec le tag z.
	// A la fin, la liaison devra donc avoir seulement le tag "z", car plus haut, le cycle0
	// a été retiré de la liste des cycles d'instance.
	$ret = db_select_all("* FROM session WHERE id = 1");
	$retx = add_cycle_to_session($ret[0]["id"], "cycle0", "z");
	$this->assertSame($retx->is_error(), false);
	$rety = db_select_all("
           * FROM session_school_year
           WHERE id_school_year = 1 AND id_session = ".$ret[0]["id"]."
	");
	$this->assertSame(count($rety), 1);
	$this->assertSame($rety[0]["id_school_year"], 1); // cycle0
	$this->assertSame($rety[0]["id_session"], $ret[0]["id"]);
	$this->assertSame($rety[0]["tags"], "z");

	$retz = update_cycle_of_session($rety[0]["id_session"], "cycle0", "y");
	$this->assertSame($retz->is_error(), false);
	$rety = db_select_all("
           * FROM session_school_year
           WHERE id_school_year = 1 AND id_session = ".$ret[0]["id"]."
	");
	$this->assertSame(count($rety), 1);
	$this->assertSame($rety[0]["id_school_year"], 1); // cycle0
	$this->assertSame($rety[0]["id_session"], $ret[0]["id"]);
	$this->assertSame($rety[0]["tags"], "y");

	$retz = remove_cycle_from_session($rety[0]["id_session"], "cycle0");
	$this->assertSame($retz->is_error(), false);

	// On détruit la derniere instance, provoquant la disparition des sessions associées
	$ret = delete_instance($instance);
    }
}

