<div id="screen_<?=$support["id"]; ?>" class="support_screen">
    <div
	class="screen_media"
	style="background-size: contain; background-repeat: no-repeat; background-position: center center;"
    ></div>
    <iframe class="screen_media" src=""></iframe>
    <video class="screen_media" controls preload="metadata"
           data-support-can-upload="<?=function_exists("can_edit_supports") && can_edit_supports() ? "1" : "0"; ?>">
	<source src="">
	<!--source src="" type="video/webm" /-->
    </video>
    <audio class="screen_media" controls src="" type="audio/mpeg"></audio>
</div>
