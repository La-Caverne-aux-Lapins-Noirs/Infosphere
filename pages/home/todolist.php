<?php if (isset($User["todo"])) { ?>
    <?php foreach ($User["todo"] as $todo) { ?>
	<li id="todo<?=$todo["id"]; ?>">
	    <div style="display: inline-block; width: calc(100% - 25px);">
		- <?=$todo["content"]; ?>
	    </div>
	    <form
		action="/api/user/<?=$User["id"]; ?>/todolist/<?=$todo["id"]; ?>"
			method="delete"
			style="width: 20px; height: 20px; color: red; display: inline-block;"
	    >
		<input
		    type="button"
		    onclick="silent_submit(this, null, null, null, 'todo<?=$todo["id"]; ?>');"
		    value="&#10007;"
		    style="width: 20px; height: 20px; color: red; display: inline-block;"
		/>
	    </form>
	</li>
    <?php } ?>
<?php } ?>
