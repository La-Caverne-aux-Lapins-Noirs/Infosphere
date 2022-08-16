<script>
 function evaluate_and_send()
 {
     var json = document.getElementById("json_box");

     try
     {
	 // JSON.parse(json.value);
	 var xhr = new XMLHttpRequest();

	 xhr.addEventListener("load", function(evt) {
	     document.getElementById("rendering").style.backgroundColor = "default";
	     document.getElementById("rendering").innerHTML = xhr.responseText;
	 });
	 xhr.addEventListener("error", function(evt) {
	     document.getElementById("rendering").style.backgroundColor = "red";
	 });
	 xhr.open("POST", "/pages/scale/json_to_html.php", false);
	 xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	 xhr.send(
	     encodeURIComponent("code") + "=" + encodeURIComponent(json.value)
	 );
     }
     catch (e)
     {
	 console.log(e);
     }
 }

 var request = null;
 function set_evaluation_request()
 {
     if (request != null)
	 clearTimeout(request);
     request = setTimeout(evaluate_and_send, 1000);
 }
</script>
<table style="height: 90%; width: 100%;"><tr><td>
    <form method="post" action="<?=unrollurl(); ?>" style="height: 100%; width: 100%;">
	<textarea
	    name="description"
	    style="width: 100%; height: 90%; font-size: xx-large; border-radius: 10px;"
	    id="json_box"
	><?=$scale["content"]; ?></textarea>
	<input
	    type="submit"
	    value="&#10003;"
	    style="width: 100%; height: 9%; border-radius: 10px; margin-top: 1%;"
	/>
    </form>
</td><td>
    <div style="width: 99%; height: 99%; margin-left: 0.5%; margin-top: 0.5%; border: 1px solid black; border-radius: 10px;" id="rendering">
    </div>
</td></tr></table>
<script>
 var json_box = document.getElementById("json_box");

 json_box.addEventListener("input", set_evaluation_request);
</script>
