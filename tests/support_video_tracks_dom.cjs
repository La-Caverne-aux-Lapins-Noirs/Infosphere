// DOM unit tests. jsdom has no media decoder: mock only the browser media APIs,
// while exercising the production controls, callbacks and resource cleanup.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {spawnSync} = require('node:child_process');
const {JSDOM} = require('jsdom');
const root = path.resolve(__dirname, '..');
const dom = new JSDOM('<div id="screen"><video data-support-can-upload="1"><source></video></div>', {
    url: 'https://infosphere.test/', runScripts: 'outside-only'
});
const w = dom.window;
const video = w.document.querySelector('video');
const turn = () => new Promise(resolve => setImmediate(resolve));
class Tracks extends w.EventTarget {
    constructor() { super(); this.length = 0; }
    add(track) { this[this.length++] = track; this.dispatchEvent(new w.Event('addtrack')); }
    remove(track) {
        const entries = Array.from(this).filter(entry => entry !== track);
        for (let i = 0; i < this.length; i++) delete this[i];
        this.length = 0;
        for (const entry of entries) this[this.length++] = entry;
        this.dispatchEvent(new w.Event('removetrack'));
    }
}
const textTracks = new Tracks();
const nativeAudio = new Tracks();
Object.defineProperty(video, 'textTracks', {value: textTracks});
Object.defineProperty(video, 'audioTracks', {value: nativeAudio});
let useNative = false;
video.canPlayType = () => useNative ? 'probably' : '';
video.pause = () => {};
video.play = () => Promise.resolve();
video.load = () => {};
Object.defineProperty(w.HTMLTrackElement.prototype, 'track', {get() {
    if (!this._track) {
        let mode = 'disabled';
        const list = this.parentElement.textTracks;
        this._track = {kind: this.kind, label: this.label, language: this.srclang,
            get mode() { return mode; }, set mode(value) { mode = value; list.dispatchEvent(new w.Event('change')); }};
        list.add(this._track);
    }
    return this._track;
}});
const remove = w.Element.prototype.remove;
w.Element.prototype.remove = function() {
    if (this._track && this.parentElement?.textTracks) this.parentElement.textTracks.remove(this._track);
    remove.call(this);
};
class Hls {
    static Events = {MANIFEST_PARSED: 'manifest', AUDIO_TRACKS_UPDATED: 'tracks', AUDIO_TRACK_SWITCHED: 'switched'};
    static isSupported() { return true; }
    constructor() {
        this.listeners = {};
        this.audioTracks = [{name: 'Français'}, {name: 'Anglais <img src=x>'}];
        this.selected = 0;
    }
    on(event, fn) { (this.listeners[event] ||= []).push(fn); }
    emit(event) { for (const fn of this.listeners[event] || []) fn(); }
    loadSource() { queueMicrotask(() => this.emit(Hls.Events.MANIFEST_PARSED)); }
    attachMedia() {}
    destroy() { this.destroyed = true; this.listeners = {}; }
    get audioTrack() { return this.selected; }
    set audioTrack(value) { this.selected = value; this.emit(Hls.Events.AUDIO_TRACK_SWITCHED); }
}
w.Hls = Hls;
w.AbortController = AbortController;
const urls = new Map();
const revoked = [];
w.Blob = Blob;
w.URL.createObjectURL = blob => {const url = 'blob:test-' + urls.size; urls.set(url, blob); return url;};
w.URL.revokeObjectURL = url => revoked.push(url);
let slowResolve;
let metadata = {subtitles: [
    {src: 'subtitle_0.vtt', label: 'Français', language: 'fr', default: true},
    {src: 'subtitle_1.vtt', label: 'Anglais', language: 'en'},
    {src: 'https://untrusted.invalid/subtitle.vtt', label: 'Invalid'}
], warnings: []};
w.fetch = async url => {
    if (/\/slow(?:\.hls|\/)/.test(String(url))) return new Promise(resolve => {slowResolve = resolve;});
    if (/\/legacy(?:\.hls|\/)/.test(String(url))) return {status: 404, ok: false};
    return {status: 200, ok: true, json: async () => metadata};
};
function render(name) {
    const result = spawnSync(process.env.INFOSPHERE_TEST_PHP || 'php', ['-n', '-r',
        '$Dictionnary=["DownloadFile"=>"Download","ServerReturnErrorStatus"=>"Server error"]; $User=["id"=>1]; $Position="support"; include $argv[1];',
        path.join(root, 'script', name)], {encoding: 'utf8'});
    assert.equal(result.status, 0, result.stderr);
    return result.stdout;
}
w.eval(render('multipanel.js'));
w.eval(fs.readFileSync(path.join(root, 'script/support_video_tracks.js'), 'utf8'));
const audio = () => w.document.querySelector('[aria-label="Piste audio"]');
const subs = () => w.document.querySelector('[aria-label="Sous-titres"]');
const warning = () => w.document.querySelector('.support_video_track_warning').textContent;
function choose(select, value) {select.value = String(value); select.dispatchEvent(new w.Event('change'));}
async function play(source = 'movie') {
    w.support_video_play(video, '/assets/' + source + '.mp4', '/assets/' + source + '.hls/index.m3u8');
    await turn();
}
(async () => {
    await play();
    assert.equal(audio().options.length, 2);
    assert.equal(subs().options.length, 3, 'External URLs must be ignored');
    assert.equal(textTracks[0].mode, 'showing');
    assert.equal(textTracks[1].mode, 'disabled');
    assert.equal(w.document.querySelectorAll('img').length, 0, 'Track names must remain plain text');
    video.currentTime = 12;
    choose(audio(), 1);
    assert.equal(video._infosphere_hls.audioTrack, 1);
    assert.equal(video.currentTime, 12);
    choose(subs(), 1);
    assert.equal(textTracks[0].mode, 'disabled');
    assert.equal(textTracks[1].mode, 'showing');
    choose(subs(), -1);
    assert.ok(Array.from(textTracks).every(track => track.mode == 'disabled'));
    const input = w.document.querySelector('input[type="file"]');
    Object.defineProperty(input, 'files', {configurable: true, value: [{name: 'local.srt', size: 80,
        text: async () => '\uFEFF1\r\n00:00:01,000 --> 00:00:04,000\r\nLocal\r\n'}]});
    await input.onchange();
    assert.equal(subs().selectedOptions[0].text, 'local.srt');
    const vtt = await Array.from(urls.values())[0].text();
    assert.ok(vtt.startsWith('WEBVTT\n\n'));
    assert.ok(vtt.includes('00:00:01.000 --> 00:00:04.000'));
    const previousHls = video._infosphere_hls;
    await play('second');
    assert.equal(w.document.querySelectorAll('.support_video_controls').length, 1);
    assert.equal(subs().options.length, 3);
    assert.ok(previousHls.destroyed);
    assert.equal(revoked.length, 1);
    await play('legacy');
    assert.equal(subs().options.length, 1);
    assert.equal(warning(), '');
    await play('slow');
    metadata = {subtitles: [], warnings: []};
    await play('new');
    slowResolve({status: 200, ok: true, json: async () => ({subtitles: [{src: 'subtitle_9.vtt', label: 'Stale'}]})});
    await turn();
    assert.equal(subs().options.length, 1, 'Stale request affected next asset');
    nativeAudio.add({label: 'VF', enabled: true});
    nativeAudio.add({label: 'VO', enabled: false});
    useNative = true;
    await play('native');
    assert.equal(video._infosphere_hls, null);
    choose(audio(), 1);
    assert.equal(nativeAudio[0].enabled, false);
    assert.equal(nativeAudio[1].enabled, true);
    const badInput = w.document.querySelector('input[type="file"]');
    Object.defineProperty(badInput, 'files', {value: [{name: 'bad.srt', size: 10, text: async () => 'not subtitles'}]});
    await badInput.onchange();
    assert.ok(warning().includes('SRT ou WebVTT'));
    let errorMessage = '';
    w.set_error_div = message => { errorMessage = message; };
    w.XMLHttpRequest = class {
        constructor() { this.status = 413; this.upload = {}; }
        addEventListener(event, fn) { if (event == 'load') this.load = fn; }
        open() {}
        setRequestHeader() {}
        send() {this.load();}
    };
    w.eval(render('send_ajax.js'));
    let success = null;
    w.send_ajax('POST', '/upload', null, null, null, null, null, result => {success = result;});
    assert.equal(success, false);
    assert.ok(errorMessage.includes('taille maximale'));
    metadata = {subtitles: [
        {src: 'subtitle_0.vtt', label: 'Forced FR', language: 'fr', default: true},
        {src: 'subtitle_1.vtt', label: 'Forced EN', language: 'en', default: true}
    ], warnings: []};
    useNative = false;
    await play('forced');
    assert.equal(Array.from(textTracks).filter(track => track.mode == 'showing').length, 1,
        'Only one forced subtitle track may be displayed');
    for (const permission of [null, '0']) {
        if (permission === null) video.removeAttribute('data-support-can-upload');
        else video.setAttribute('data-support-can-upload', permission);
        await play('reader');
        assert.equal(w.document.querySelectorAll('input[type="file"]').length, 0,
            'Local subtitle loader must be absent for readers and when permission is unspecified');
        assert.equal(w.document.querySelectorAll('.support_video_local_subtitles').length, 0);
        assert.equal(audio().options.length, 2, 'Readers must retain the audio selector');
        assert.equal(subs().options.length, 3, 'Readers must retain existing subtitles');
        choose(audio(), 1);
        choose(subs(), 1);
        assert.equal(video._infosphere_hls.audioTrack, 1);
        assert.equal(textTracks[1].mode, 'showing');
    }
    video.setAttribute('data-support-can-upload', '1');
    await play('editor');
    assert.equal(w.document.querySelectorAll('input[type="file"]').length, 1,
        'Editors must retain the local subtitle loader');
    w.support_video_reset_player(video);
    assert.equal(w.document.querySelectorAll('.support_video_controls').length, 0);
    console.log('OK: DOM audio selection, subtitle selection/off, forced default, local SRT conversion, safe labels/URLs, asset lifecycle, stale requests, native audio, errors, HTTP 413 and subtitle-loader permissions.');
    dom.window.close();
})().catch(error => {console.error(error); dom.window.close(); process.exitCode = 1;});
