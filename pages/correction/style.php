<style>
.correction-admin { width: 100%; color: #f7fff7; }
.correction-admin * { box-sizing: border-box; }
.correction-heading { margin-bottom:8px; }
.correction-heading h2 { margin:0 0 4px; }
.correction-heading p { margin:0; opacity:.86; }

/* Les commandes occupent toute la largeur afin de ne pas écraser l’en-tête. */
.correction-actions {
    display:grid;
    grid-template-columns:repeat(6, minmax(0, 1fr));
    gap:6px;
    width:100%;
    margin:8px 0 10px;
}
.correction-actions form {
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
    min-width:0;
    padding:6px 8px;
    border:1px solid rgba(0,220,0,.28);
    border-radius:10px;
    background:rgba(0,0,0,.72);
}
.correction-actions > form:nth-child(-n+3) { grid-column:span 2; }
.correction-actions .correction-upload { grid-column:span 3; }
.correction-actions .correction-category { grid-column:span 3; }
.correction-actions input,
.correction-actions select,
.correction-actions button,
.correction-search {
    border-radius:7px;
    padding:7px 9px;
    border:1px solid rgba(0,0,0,.45);
}
.correction-actions input[type="text"] { flex:1 1 260px; min-width:180px; }
.correction-actions input[type="file"] { flex:1 1 340px; min-width:220px; }
.correction-actions select { flex:0 1 260px; min-width:160px; }
.correction-actions button { background:rgba(0,90,0,.9); color:white; cursor:pointer; }
.correction-upload-status {
    display:flex;
    align-items:center;
    gap:10px;
    margin:-2px 0 10px;
    padding:8px 10px;
    border:1px solid rgba(0,220,0,.28);
    border-radius:9px;
    background:rgba(0,0,0,.72);
}
.correction-upload-status[hidden] { display:none; }
.correction-upload-status details { max-width:55%; }
.correction-upload-status details ul {
    max-height:140px;
    overflow:auto;
    margin:5px 0 0;
    padding-left:20px;
}
.correction-upload-status button {
    margin-left:auto;
    padding:5px 8px;
    border:1px solid rgba(0,220,0,.3);
    border-radius:6px;
    background:rgba(0,90,0,.9);
    color:white;
    cursor:pointer;
}

#correction-catalog {
    display:flex;
    flex-direction:column;
    gap:8px;
    height:clamp(260px, calc(100vh - 382px), 620px);
    min-height:0;
}
.correction-catalog-warning {
    flex:0 0 auto;
    max-height:150px;
    overflow-y:auto;
    padding:10px 14px;
    border:1px solid rgba(255,170,70,.55);
    border-radius:10px;
    background:rgba(70,35,0,.78);
}
.correction-catalog-warning h3 { margin:0 0 4px; }
.correction-catalog-warning p { margin:0 0 6px; }
.correction-catalog-warning ul { margin:0; padding-left:22px; }
.correction-catalog-warning .missing { color:#ffd0a0; }
.correction-admin .tabpanel {
    position:relative;
    flex:1 1 auto;
    width:100%;
    height:auto;
    min-height:0;
}
.correction-admin .tablist {
    position:relative;
    top:auto;
    left:auto;
    display:flex;
    align-items:stretch;
    gap:6px;
    width:100%;
    height:34px;
    margin:0 0 6px;
    padding:0;
}
.correction-admin .tablist > a {
    display:flex;
    align-items:stretch;
    flex:1 1 0;
    min-width:0;
    margin:0;
    padding:0;
    text-decoration:none;
}
.correction-tab-button {
    display:flex;
    align-items:center;
    justify-content:center;
    width:100% !important;
    height:34px;
    min-height:34px;
    margin:0;
    padding:5px 10px;
    line-height:1.2;
    text-align:center;
    border-radius:9px;
    border:1px solid rgba(0,220,0,.3);
    background:rgba(0,0,0,.76);
    color:white;
}
.correction-tab-button.selected { background:rgba(0,110,0,.85); font-weight:bold; }
.correction-admin .tabcontent {
    position:relative;
    top:auto;
    left:auto;
    width:100%;
    height:calc(100% - 40px);
    min-height:0;
    overflow:auto;
}
.correction-admin .tabcontent > div {
    position:relative;
    top:auto;
    left:auto;
    width:100%;
    height:auto;
    min-height:100%;
}
.correction-panel { display:grid; gap:12px; }
.correction-library-toolbar { display:flex; justify-content:flex-end; gap:6px; }
.correction-library-toolbar button { padding:5px 9px; border:1px solid rgba(0,220,0,.3); border-radius:7px; background:rgba(0,80,0,.88); color:white; cursor:pointer; }
.correction-library-card { padding:0; overflow:hidden; }
.correction-library-card .correction-library-header { align-items:center; margin:0; padding:12px 14px; }
.correction-library-toggle { flex:0 0 auto; width:28px; height:28px; padding:0; border:1px solid rgba(0,220,0,.3); border-radius:7px; background:rgba(0,70,0,.72); color:white; cursor:pointer; font-size:1.05em; line-height:1; }
.correction-library-toggle[aria-expanded="false"] span { display:inline-block; transform:rotate(-90deg); }
.correction-library-title { min-width:0; flex:1 1 auto; }
.correction-library-title-line { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.correction-library-title-line h3 { margin:0; }
.correction-library-body { padding:0 14px 14px; border-top:1px solid rgba(0,220,0,.14); }
.correction-library-card.collapsed .correction-library-body { display:none; }
.correction-grid { grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); }
.correction-card { background:rgba(0,0,0,.80); border:1px solid rgba(0,220,0,.28); border-radius:12px; padding:14px; overflow:auto; }
.correction-card.compact { min-height:150px; }
.correction-card header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:12px; }
.correction-card-actions { display:flex; align-items:center; justify-content:flex-end; gap:6px; flex-wrap:wrap; }
.correction-card-actions form { margin:0; }
.correction-move-form { display:flex; align-items:center; gap:5px; padding:3px; border:1px solid rgba(0,220,0,.22); border-radius:8px; background:rgba(255,255,255,.04); }
.correction-move-form label { display:flex; min-width:0; }
.correction-move-form select { width:190px; max-width:28vw; min-width:120px; padding:5px 7px; border-radius:6px; }
.correction-move-form button { padding:5px 8px; border-radius:6px; border:1px solid rgba(0,220,0,.3); background:rgba(0,90,0,.9); color:white; cursor:pointer; }
.correction-table-actions { min-width:285px; flex-wrap:nowrap; }
.correction-move-form-compact select { width:155px; max-width:18vw; min-width:105px; }
.correction-visually-hidden { position:absolute !important; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
.correction-card h3 { margin:0 0 4px; }
.correction-card code, .correction-table code { overflow-wrap:anywhere; }
.correction-metadata { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:8px; margin:0 0 12px; }
.correction-metadata div { padding:8px; background:rgba(255,255,255,.06); border-radius:8px; }
.correction-metadata dt { font-size:.8em; opacity:.75; }
.correction-metadata dd { margin:4px 0 0; }
.correction-table { width:100%; }
.correction-table .ok { color:#9dff9d; }
.correction-table .missing { color:#ffaaaa; font-weight:bold; }
.status { display:inline-block; border-radius:999px; padding:3px 8px; font-size:.84em; }
.status.complete { background:rgba(0,130,0,.72); }
.status.incomplete { background:rgba(155,70,0,.82); }
button.danger { background:rgba(125,0,0,.82); color:white; border:1px solid rgba(255,100,100,.4); border-radius:7px; padding:6px 9px; cursor:pointer; }
.correction-search { width:min(520px,100%); background:white; color:#111; }
.correction-empty, .correction-muted { opacity:.78; }

.correction-sync-status { margin:0 0 16px; padding:12px 14px; border:1px solid #bbb; border-radius:6px; }
.correction-sync-status.error { border-color:#b33; }
.correction-sync-summary { display:flex; flex-wrap:wrap; gap:14px; margin:8px 0; }
.correction-sync-details { margin-top:8px; }
.correction-sync-details ul { margin:6px 0 0 20px; }


.correction-tree-panel { display:flex; flex-direction:column; gap:8px; min-height:100%; }
.correction-tree-toolbar { display:flex; align-items:center; gap:7px; flex-wrap:wrap; padding:8px 10px; border:1px solid rgba(0,220,0,.25); border-radius:9px; background:rgba(0,0,0,.70); }
.correction-tree-toolbar button,
.correction-tree-actions button,
.correction-tree-actions a.button { display:inline-flex; align-items:center; padding:4px 7px; border:1px solid rgba(0,220,0,.3); border-radius:6px; background:rgba(0,80,0,.88); color:white; cursor:pointer; }
.correction-tree-toolbar span { margin-left:auto; opacity:.72; font-size:.9em; }
.correction-tree-root { flex:1 1 auto; min-height:0; overflow:auto; padding:8px; border:1px solid rgba(0,220,0,.25); border-radius:10px; background:rgba(0,0,0,.78); }
.correction-tree-root.drag-over,
.correction-tree-row.drag-over { outline:2px solid rgba(120,255,120,.9); outline-offset:-2px; background:rgba(0,110,0,.38); }
.correction-tree-root-label { display:flex; align-items:center; gap:8px; padding:6px 8px; border-bottom:1px solid rgba(255,255,255,.10); }
.correction-tree-root-label small { opacity:.65; }
.correction-tree,
.correction-tree ul { list-style:none; margin:0; padding-left:20px; }
.correction-tree-root-list { padding-left:4px; }
.correction-tree-node { margin:1px 0; }
.correction-tree-node[draggable="true"] { cursor:grab; }
.correction-tree-node.dragging { opacity:.45; }
.correction-tree-node details > summary { list-style:none; }
.correction-tree-node details > summary::-webkit-details-marker { display:none; }
.correction-tree-row { display:grid; grid-template-columns:18px 18px 24px minmax(150px, 1fr) minmax(120px, .7fr) auto; align-items:center; gap:5px; min-height:31px; padding:3px 6px; border-radius:6px; }
.correction-tree-row:hover { background:rgba(255,255,255,.06); }
.correction-tree-folder { font-weight:600; }
.correction-tree-file { grid-template-columns:18px 18px 24px minmax(150px, 1fr) minmax(90px, .4fr) auto; }
.correction-tree-file.correction-tree-editable { cursor:default; }
.correction-tree-file.correction-tree-editable .correction-tree-name { cursor:pointer; }
.correction-tree-expander { width:14px; text-align:center; opacity:.8; }
.correction-tree-folder-node > details > summary .correction-tree-expander::before { content:'▾'; }
.correction-tree-folder-node > details:not([open]) > summary .correction-tree-expander::before { content:'▸'; }
.correction-tree-icon { width:22px; text-align:center; }
.correction-tree-name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.correction-tree-path,
.correction-tree-kind { opacity:.62; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:.88em; }
.correction-tree-actions { display:flex; align-items:center; justify-content:flex-end; gap:4px; opacity:.18; transition:opacity .12s ease; }
.correction-tree-row:hover .correction-tree-actions,
.correction-tree-row:focus-within .correction-tree-actions { opacity:1; }
.correction-tree-actions button.danger { background:rgba(115,0,0,.88); }
.correction-tree-children.empty { min-height:5px; }
@media (max-width: 850px) {
    .correction-tree-row,
    .correction-tree-file { grid-template-columns:18px 18px 24px minmax(120px,1fr) auto; }
    .correction-tree-path,
    .correction-tree-kind { display:none; }
    .correction-tree-actions { opacity:1; }
    .correction-tree-toolbar span { width:100%; margin-left:0; }
}

@media (max-width: 1100px) {
    .correction-actions { grid-template-columns:repeat(2, minmax(0, 1fr)); }
    .correction-actions > form:nth-child(-n+3),
    .correction-actions .correction-upload,
    .correction-actions .correction-category { grid-column:span 1; }
    .correction-actions .correction-category { grid-column:span 2; }
    #correction-catalog { height:clamp(240px, calc(100vh - 430px), 560px); }
}
@media (max-width: 700px) {
    .correction-actions { grid-template-columns:1fr; }
    .correction-actions > form:nth-child(-n+3),
    .correction-actions .correction-upload,
    .correction-actions .correction-category { grid-column:auto; }
    #correction-catalog { height:auto; min-height:320px; }
    .correction-admin .tabpanel { height:auto; min-height:320px; }
    .correction-admin .tabcontent { height:auto; min-height:270px; }
}
@media (max-width: 650px) {
    .correction-admin .tablist { flex-direction:column; }
}
</style>
