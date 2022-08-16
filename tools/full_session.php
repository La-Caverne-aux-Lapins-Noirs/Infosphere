<?php

class FullSession
{
    public $id;
    public $id_activity = -1;
    public $id_team = -1;
    public $id_laboratory = -1;
    public $id_user = -1;
    public $is_template = false;
    public $id_template = -1;
    public $template_link = true;
    public $begin_date = NULL;
    public $end_date = NULL;
    public $start_hour = NULL;
    public $start_minute = NULL;
    public $end_hour = NULL;
    public $end_minute = NULL;
    public $slot = [];
    public $room = [];
    public $db_maximum_subscription = -1;
    public $maximum_subscription = -1;
    public $room_space = -1;
    public $room_full = false;
    public $current_occupation = -1;
    public $team = [];
    public $nbr_students = 0;
    public $parent = NULL;
    public $registered = false;
    public $user_team = NULL;
    public $leader = false;
    public $slot_reserved = false;
    public $user_slot = NULL;
    public $deleted = false;

    // Pour le calendrier... ne pas s'en servir
    public $local_start;
    public $local_end;
    public $top;
    public $height;
    public $left;
    public $width;

    public function build_session($id, $user = NULL, $only_user = false)
    {
	$id = (int)$id;
	$session = db_select_one("* FROM session WHERE id = $id");
	if ($session["id_activity"] != -1)
	{
	    $this->id_activity = $session["id_activity"];
	    ($activity = new FullActivity)->build($session["id_activity"]);
	}
	else
	    $activity = NULL;
	return ($this->build($session, $activity, $user, $only_user));
    }
    
    public function build($session, &$parent, $user = NULL, $only_user = false)
    {
	global $Language;
	global $User;
	global $one_day;
	global $one_hour;

	if ($user == NULL)
	    $user = $User;
	$this->parent = &$parent;
	transfert(
	    ["id", "begin_date", "end_date", "is_template", "id_template",
	     "template_link", "id_activity", "id_user", "id_laboratory", "id_team",
	     "maximum_subscription", "deleted"
	    ], $this, $session
	);
	to_timestamp($this->begin_date);
	$this->start_hour = $this->begin_date % $one_day / 60 / 60;
	$this->start_minute = $this->begin_date % $one_hour / 60;
	to_timestamp($this->end_date);
	$this->end_hour = $this->end_date % $one_day / 60 / 60;
	$this->end_minute = $this->end_date % $one_hour / 60;
	$this->db_maximum_subscription = $this->maximum_subscription;

	$this->room = fetch_link("session", "room", $this->id, true, ["name"])->value;
	if (count($this->room))
	    $this->room_space = 0;
	foreach ($this->room as $k => $rom)
	{
	    if ($rom["capacity"] == -1)
	    {
		$this->room_space = -1;
		break ;
	    }
	    $this->room_space += $rom["capacity"];
	}
	if ($this->room_space != -1)
	{
	    if ($this->maximum_subscription == -1 || $this->maximum_subscription > $this->room_space)
		$this->maximum_subscription = $this->room_space;
	}

	/// Equipe
	if ($only_user && $user)
	{
	    $limit = " AND user_team.id_user = {$user["id"]} ";
	    $selteam = " LEFT JOIN user_team ON team.id = user_team.id_team ";
	}
	else
	{
	    $limit = "";
	    $selteam = "";
	}
	if ($parent->reference_activity == -1)
	    $ref = "team.id_session = ".$session["id"];
	else
	    $ref = "team.id_activity = ".$parent->reference_activity;
	$this->team = db_select_all("
           team.*
           FROM team
           $selteam
           WHERE $ref
           $limit
	", "id");
	foreach ($this->team as &$team)
	{
	    $team["slot"] = false;
	    $team["user"] = db_select_all("
              user.codename as codename,
              user.id as id,
              user_team.status as status
              FROM user_team
              LEFT JOIN user ON user_team.id_user = user.id
              WHERE user_team.id_team = {$team["id"]}
	      ", "id");
	    $this->nbr_students += count($team["user"]);
	    $team["work"] = db_select_all("
              *
              FROM pickedup_work
              WHERE id_team = {$team["id"]}
              ORDER BY pickedup_date DESC
	      ");
	    if ($this->registered == false)
	    {
		foreach ($team["user"] as $u)
		{
		    if ($u["id"] == $user["id"])
		    {
			$this->user_team = $team;
			$this->leader = $u["status"];
			$this->registered = true;
			break ;
		    }
		}
	    }
	}

	$this->current_occupation = $this->parent->min_team_size * count($this->team);
	$this->full = !in_limit($this->current_occupation, $this->maximum_subscription);

	$this->slot = db_select_all("
           appointment_slot.*
           FROM appointment_slot
           LEFT JOIN team ON team.id = appointment_slot.id_team
           WHERE appointment_slot.id_session = {$session["id"]}
           ORDER BY begin_date ASC
	   ", "id");
	foreach ($this->slot as &$slot)
	{
	    if ($slot["id_team"] <= 0)
		continue ;
	    if ($this->user_team != NULL && $this->user_team["id"] == $slot["id_team"])
	    {
		$this->slot_reserved = true;
		$this->user_slot = &$slot;
	    }
	    if (isset($this->team[$slot["id_team"]]))
	    {
		$slot["team"] = &$this->team[$slot["id_team"]];
		$this->team[$slot["id_team"]]["slot"] = true;
	    }
	}
    }
}
