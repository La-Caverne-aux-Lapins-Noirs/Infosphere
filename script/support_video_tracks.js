// The media element is reused when switching between support assets. Keep
// track listeners, requests and object URLs tied to that element's lifecycle.
function support_video_tracks_cleanup(video)
{
    video._infosphere_track_generation = (video._infosphere_track_generation || 0) + 1;
    if (video._infosphere_track_request)
        video._infosphere_track_request.abort();
    video._infosphere_track_request = null;
    for (let cleanup of video._infosphere_track_cleanup || [])
        cleanup();
    video._infosphere_track_cleanup = [];
    for (let url of video._infosphere_track_urls || [])
        URL.revokeObjectURL(url);
    video._infosphere_track_urls = [];
    for (let track of Array.from(video.querySelectorAll("track[data-infosphere-track]")))
        track.remove();
    if (video._infosphere_track_controls)
        video._infosphere_track_controls.remove();
    video._infosphere_track_controls = null;
    video._infosphere_subtitle_choice = null;
}

function support_video_tracks_listen(video, target, event, callback)
{
    if (!target || !target.addEventListener)
        return ;
    target.addEventListener(event, callback);
    video._infosphere_track_cleanup.push(function() {
        target.removeEventListener(event, callback);
    });
}

function support_video_tracks_initialize(video, hls_asset)
{
    let generation = video._infosphere_track_generation;
    let can_upload = video.getAttribute("data-support-can-upload") === "1";
    let controls = document.createElement("div");
    let audio_label = document.createElement("label");
    let audio_select = document.createElement("select");
    let subtitle_label = document.createElement("label");
    let subtitle_select = document.createElement("select");
    let local_label = document.createElement("label");
    let local_input = document.createElement("input");
    let warning = document.createElement("span");
    let fullscreen = document.createElement("button");
    controls.className = "support_video_controls";
    controls.setAttribute("role", "group");
    controls.setAttribute("aria-label", "Langues et sous-titres");
    audio_label.appendChild(document.createTextNode("Audio "));
    audio_select.setAttribute("aria-label", "Piste audio");
    audio_label.appendChild(audio_select);
    subtitle_label.appendChild(document.createTextNode("Sous-titres "));
    subtitle_select.setAttribute("aria-label", "Sous-titres");
    subtitle_label.appendChild(subtitle_select);
    local_label.className = "support_video_local_subtitles";
    local_label.appendChild(document.createTextNode("Charger des sous-titres…"));
    local_label.title = "Fichier SRT ou VTT utilisé dans ce navigateur";
    local_input.type = "file";
    local_input.accept = ".srt,.vtt,text/vtt,application/x-subrip";
    local_input.setAttribute("aria-label", "Charger un fichier de sous-titres SRT ou VTT");
    local_label.appendChild(local_input);
    warning.className = "support_video_track_warning";
    warning.setAttribute("role", "status");
    controls.append(audio_label, subtitle_label);
    if (can_upload)
        controls.appendChild(local_label);
    controls.appendChild(warning);
    fullscreen.type = "button";
    fullscreen.textContent = "Plein écran";
    fullscreen.hidden = !video.parentElement.requestFullscreen;
    fullscreen.onclick = function() {
        video.parentElement.requestFullscreen().catch(function() {
            warning.textContent = "Impossible de passer en plein écran.";
        });
    };
    controls.insertBefore(fullscreen, warning);
    video.parentElement.appendChild(controls);
    video._infosphere_track_controls = controls;
    video._infosphere_track_cleanup = [];
    video._infosphere_track_urls = [];

    function refresh_audio()
    {
        let hls = video._infosphere_hls;
        let tracks = hls ? hls.audioTracks : (video.audioTracks || []);
        let selected = hls ? hls.audioTrack : -1;
        audio_select.replaceChildren();
        for (let i = 0; i < tracks.length; ++i)
        {
            let track = tracks[i];
            audio_select.add(new Option(track.name || track.label || track.lang || track.language || "Piste " + (i + 1), String(i)));
            if (!hls && track.enabled)
                selected = i;
        }
        audio_select.value = String(selected);
        audio_label.hidden = tracks.length < 2;
    }

    function refresh_subtitles()
    {
        let tracks = video.textTracks;
        let selected = -1;
        subtitle_select.replaceChildren(new Option("Désactivés", "-1"));
        for (let i = 0; i < tracks.length; ++i)
        {
            let track = tracks[i];
            if (track.kind != "subtitles" && track.kind != "captions")
                continue ;
            subtitle_select.add(new Option(track.label || track.language || "Piste " + (i + 1), String(i)));
            if (track.mode == "showing")
                selected = i;
        }
        subtitle_select.value = String(selected);
        subtitle_select.disabled = subtitle_select.options.length == 1;
    }

    function add_subtitle(src, label, language, default_track = false)
    {
        let element = document.createElement("track");
        element.kind = "subtitles";
        element.label = label;
        element.srclang = language || "und";
        element.src = src;
        element.setAttribute("data-infosphere-track", "1");
        element.default = default_track && video._infosphere_subtitle_choice === null
            && !Array.from(video.textTracks).some(function(track) { return track.mode == "showing"; });
        video.appendChild(element);
        if (element.default)
            element.track.mode = "showing";
        else
            element.track.mode = "disabled";
        element.addEventListener("error", function() {
            if (video._infosphere_track_generation == generation)
                warning.textContent = "Impossible de charger les sous-titres « " + label + " ».";
        });
        refresh_subtitles();
        return (element);
    }

    audio_select.onchange = function() {
        let selected = Number(audio_select.value);
        if (video._infosphere_hls)
            video._infosphere_hls.audioTrack = selected;
        else if (video.audioTracks)
            for (let i = 0; i < video.audioTracks.length; ++i)
                video.audioTracks[i].enabled = i == selected;
    };
    subtitle_select.onchange = function() {
        let selected = Number(subtitle_select.value);
        video._infosphere_subtitle_choice = selected;
        for (let i = 0; i < video.textTracks.length; ++i)
            if (video.textTracks[i].kind == "subtitles" || video.textTracks[i].kind == "captions")
                video.textTracks[i].mode = i == selected ? "showing" : "disabled";
    };
    local_input.onchange = async function() {
        if (!can_upload)
            return ;
        let file = local_input.files[0];
        if (!file)
            return ;
        if (file.size > 10 * 1024 * 1024)
        {
            warning.textContent = "Le fichier de sous-titres dépasse 10 Mo.";
            return ;
        }
        try
        {
            let text = (await file.text()).replace(/^\uFEFF/, "").replace(/\r\n?/g, "\n");
            if (video._infosphere_track_generation != generation)
                return ;
            if (!/^WEBVTT(?:[ \t]|\n|$)/.test(text))
            {
                if (!/\d{2}:\d{2}:\d{2},\d{3}\s+-->/.test(text))
                    throw new Error("Format attendu : SRT ou WebVTT.");
                text = "WEBVTT\n\n" + text.replace(/(\d{2}:\d{2}:\d{2}),(\d{3})/g, "$1.$2");
            }
            let url = URL.createObjectURL(new Blob([text], {type: "text/vtt;charset=utf-8"}));
            video._infosphere_track_urls.push(url);
            let element = add_subtitle(url, file.name, "und");
            for (let track of Array.from(video.textTracks))
                if (track.kind == "subtitles" || track.kind == "captions")
                    track.mode = track == element.track ? "showing" : "disabled";
            video._infosphere_subtitle_choice = Array.from(video.textTracks).indexOf(element.track);
            refresh_subtitles();
            warning.textContent = "";
        }
        catch (error)
        {
            if (video._infosphere_track_generation == generation)
                warning.textContent = error.message || "Impossible de lire ce fichier de sous-titres.";
        }
        local_input.value = "";
    };
    support_video_tracks_listen(video, video, "loadedmetadata", function() {
        refresh_audio();
        refresh_subtitles();
    });
    for (let event of ["addtrack", "removetrack", "change"])
    {
        support_video_tracks_listen(video, video.audioTracks, event, refresh_audio);
        support_video_tracks_listen(video, video.textTracks, event, refresh_subtitles);
    }
    refresh_audio();
    refresh_subtitles();
    // hls.js is attached just after initialization; bind its event emitter then.
    video._infosphere_refresh_audio_tracks = refresh_audio;
    if (hls_asset)
    {
        let controller = new AbortController();
        video._infosphere_track_request = controller;
        let metadata_url = new URL("tracks.json", new URL(hls_asset, document.baseURI));
        fetch(metadata_url, {signal: controller.signal, credentials: "same-origin"})
            .then(function(response) {
                // Old HLS packages have no sidecar; playback remains supported.
                if (response.status == 404)
                    return (null);
                if (!response.ok)
                    throw new Error("Liste des sous-titres indisponible.");
                return (response.json());
            }).then(function(metadata) {
                if (!metadata || video._infosphere_track_generation != generation)
                    return ;
                let base = new URL(".", metadata_url);
                for (let track of metadata.subtitles || [])
                {
                    // Sidecars are generated by the server, but never accept an
                    // external URL or a parent path as a subtitle source.
                    if (!/^subtitle_[0-9]+\.vtt$/.test(track.src))
                        continue ;
                    add_subtitle(new URL(track.src, base).href, track.label || "Sous-titres", track.language, !!track.default);
                }
                warning.textContent = (metadata.warnings || []).join(" ");
            }).catch(function(error) {
                if (error.name != "AbortError" && video._infosphere_track_generation == generation)
                    warning.textContent = error.message || "Liste des sous-titres indisponible.";
            });
    }
}

function support_video_tracks_bind_hls(video)
{
    let hls = video._infosphere_hls;
    let refresh = video._infosphere_refresh_audio_tracks;
    for (let event of [Hls.Events.MANIFEST_PARSED, Hls.Events.AUDIO_TRACKS_UPDATED, Hls.Events.AUDIO_TRACK_SWITCHED])
        if (event)
            hls.on(event, refresh);
}
