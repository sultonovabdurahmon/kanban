const $ = s => document.querySelector(s);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const PR = {low: 'Паст', medium: 'Миёна', high: 'Баланд'};
const S = {me: null, pid: null, board: null, f: {assignee: '', from: '', to: ''}, drag: null};

async function api(action, data = {}) {
  const opt = data instanceof FormData
    ? {method: 'POST', body: data}
    : {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data)};
  const r = await fetch('api.php?action=' + action, opt);
  const txt = await r.text();
  let j;
  try { j = JSON.parse(txt); } catch (e) { throw new Error('api.php вернул не JSON (' + r.status + '): ' + txt.slice(0, 300)); }
  if (!r.ok) throw new Error(j.error || 'Хато');
  return j;
}

/* ---------- Модал ---------- */
function openModal(html) { $('#modalBox').innerHTML = html; $('#modal').classList.remove('hidden'); }
function closeModal() { $('#modal').classList.add('hidden'); $('#modalBox').innerHTML = ''; }
$('#modal').addEventListener('mousedown', e => { if (e.target.id === 'modal') closeModal(); });

/* ---------- Оғоз ---------- */
async function init() {
  try {
    S.me = (await api('me')).me;
    renderUser();
    S.me ? showProjects() : showAuth('login');
  } catch (e) {
    $('#app').innerHTML = '<div class="card-box"><h3>Ошибка запуска</h3><pre style="white-space:pre-wrap;color:#dc2626">' + esc(e.message) + '</pre></div>';
  }
}
function renderUser() {
  $('#userbox').innerHTML = S.me
    ? `${esc(S.me.name)} <button class="ghost" id="outBtn">Баромад</button>` : '';
  if (S.me) $('#outBtn').onclick = async () => { await api('logout'); S.me = null; S.pid = null; renderUser(); showAuth('login'); };
}

/* ---------- Воридшавӣ / сабти ном ---------- */
function showAuth(mode) {
  const reg = mode === 'register';
  $('#app').innerHTML = `<div class="card-box auth">
    <div class="tabs"><button class="${reg ? 'ghost' : ''}" id="tLogin">Ворид шудан</button>
    <button class="${reg ? '' : 'ghost'}" id="tReg">Сабти ном</button></div>
    ${reg ? '<label>Ном</label><input id="aName">' : ''}
    <label>Email</label><input id="aEmail" type="email">
    <label>Парол</label><input id="aPass" type="password">
    <div class="err" id="aErr"></div>
    <button id="aGo" style="width:100%;margin-top:8px">${reg ? 'Сабти ном' : 'Ворид шудан'}</button></div>`;
  $('#tLogin').onclick = () => showAuth('login');
  $('#tReg').onclick = () => showAuth('register');
  $('#aGo').onclick = async () => {
    try {
      await api(reg ? 'register' : 'login', {name: $('#aName')?.value, email: $('#aEmail').value, password: $('#aPass').value});
      S.me = (await api('me')).me; renderUser(); showProjects();
    } catch (e) { $('#aErr').textContent = e.message; }
  };
}

/* ---------- Лоиҳаҳо ---------- */
async function showProjects() {
  S.pid = null;
  const {projects} = await api('projects');
  $('#app').innerHTML = `<div class="card-box">
    <h2 style="margin-top:0">Лоиҳаҳои ман</h2>
    <div class="row"><div><label>Номи лоиҳаи нав</label><input id="pName"></div>
    <div><label>Тавсиф</label><input id="pDesc"></div></div>
    <div class="err" id="pErr"></div><button id="pAdd">+ Сохтани лоиҳа</button></div>
    <div class="grid">${projects.map(p => `<div class="proj" data-id="${p.id}">
      <b>${esc(p.name)}</b><p style="color:#64748b;font-size:13px">${esc(p.description)}</p>
      <span class="badge ${p.role}">${p.role === 'manager' ? 'Роҳбар' : 'Иштирокчӣ'}</span></div>`).join('') || '<p>Лоиҳа нест. Якумин лоиҳаро созед.</p>'}</div>`;
  $('#pAdd').onclick = async () => {
    try { await api('project_create', {name: $('#pName').value, description: $('#pDesc').value}); showProjects(); }
    catch (e) { $('#pErr').textContent = e.message; }
  };
  document.querySelectorAll('.proj').forEach(el => el.onclick = () => { S.pid = +el.dataset.id; S.f = {assignee: '', from: '', to: ''}; loadBoard(); });
}

/* ---------- Тахта ---------- */
async function loadBoard() {
  S.board = await api('board', {project_id: S.pid, ...S.f});
  renderBoard();
}
const today = () => new Date().toISOString().slice(0, 10);
const isLate = t => t.due_date && t.due_date < today() && +t.column_id !== S.board.last_column;

function renderBoard() {
  const b = S.board, mgr = b.role === 'manager';
  const opts = b.members.map(m => `<option value="${m.id}" ${S.f.assignee == m.id ? 'selected' : ''}>${esc(m.name)}</option>`).join('');
  $('#app').innerHTML = `
    <div class="toolbar">
      <button class="ghost" id="back">← Лоиҳаҳо</button>
      <h2 style="margin:0 10px">${esc(b.project.name)} <span class="badge ${b.role}">${mgr ? 'Роҳбар' : 'Иштирокчӣ'}</span></h2>
    </div>
    <div class="toolbar">
      ${mgr ? `<div><label>Иҷрокунанда</label><select id="fAs"><option value="">Ҳама</option>${opts}</select></div>` : ''}
      <div><label>Мӯҳлат аз</label><input type="date" id="fFrom" value="${S.f.from}"></div>
      <div><label>то</label><input type="date" id="fTo" value="${S.f.to}"></div>
      <button id="fGo">Филтр</button><button class="ghost" id="fClr">Тоза кардан</button>
      <button class="danger" id="late">⏰ Гузашта аз мӯҳлат (${b.overdue.length})</button>
      ${mgr ? `<button id="addT">+ Вазифа</button><button id="addC" class="ghost">+ Сутун</button><button id="addM" class="ghost">+ Иштирокчӣ</button>` : ''}
    </div>
    <div class="board">${b.columns.map(c => `
      <div class="col"><h3>${esc(c.title)} <span>${b.tasks.filter(t => t.column_id == c.id).length}</span></h3>
      <div class="col-body" data-col="${c.id}">${b.tasks.filter(t => t.column_id == c.id).map(cardHtml).join('')}</div></div>`).join('')}</div>`;

  $('#back').onclick = showProjects;
  $('#fGo').onclick = () => { S.f = {assignee: $('#fAs')?.value || '', from: $('#fFrom').value, to: $('#fTo').value}; loadBoard(); };
  $('#fClr').onclick = () => { S.f = {assignee: '', from: '', to: ''}; loadBoard(); };
  $('#late').onclick = showOverdue;
  if (mgr) {
    $('#addT').onclick = () => taskForm();
    $('#addC').onclick = async () => { const t = prompt('Номи сутуни нав:'); if (t) { await act('column_create', {project_id: S.pid, title: t}); } };
    $('#addM').onclick = async () => { const e = prompt('Email-и иштирокчӣ (бояд сабти ном шуда бошад):'); if (e) { await act('member_add', {project_id: S.pid, email: e}); } };
  }
  bindDnD();
}
async function act(a, d) { try { await api(a, d); await loadBoard(); } catch (e) { alert(e.message); } }

function cardHtml(t) {
  const can = S.board.role === 'manager' || t.assignee_id == S.me.id;
  return `<div class="card p-${t.priority} ${isLate(t) ? 'late' : ''} ${can ? '' : 'nodrag'}" data-id="${t.id}" draggable="${can}">
    <div class="t">${esc(t.title)}</div>
    <div class="m"><span>👤 ${esc(t.assignee_name || '—')}</span>
    <span class="${isLate(t) ? 'late-txt' : ''}">📅 ${esc(t.due_date || '—')}</span>
    <span>${PR[t.priority]}</span></div></div>`;
}

/* ---------- Кашидани корт бо муш (drag & drop + fetch) ---------- */
function bindDnD() {
  document.querySelectorAll('.card').forEach(c => {
    c.ondragstart = e => { S.drag = c.dataset.id; c.classList.add('drag'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', c.dataset.id); };
    c.ondragend = () => c.classList.remove('drag');
    c.onclick = () => openTask(c.dataset.id);
  });
  document.querySelectorAll('.col-body').forEach(col => {
    col.ondragover = e => { e.preventDefault(); col.classList.add('over'); };
    col.ondragleave = () => col.classList.remove('over');
    col.ondrop = async e => {
      e.preventDefault(); col.classList.remove('over');
      const cards = [...col.querySelectorAll('.card:not(.drag)')];
      let idx = cards.findIndex(c => e.clientY < c.getBoundingClientRect().top + c.offsetHeight / 2);
      if (idx < 0) idx = cards.length;
      const dragged = document.querySelector('.card.drag');
      if (dragged) { dragged.classList.remove('drag'); (cards[idx] ? col.insertBefore(dragged, cards[idx]) : col.appendChild(dragged)); } // нишондиҳии фаврӣ
      try { await api('task_move', {task_id: S.drag, column_id: col.dataset.col, position: idx}); }
      catch (err) { alert(err.message); }
      loadBoard(); // ҳамоҳангсозӣ бо сервер бе навсозии саҳифа
    };
  });
}

/* ---------- Вазифаҳои гузашта аз мӯҳлат ---------- */
function showOverdue() {
  const list = S.board.overdue;
  openModal(`<h3 style="margin-top:0">Вазифаҳои мӯҳлаташон гузашта</h3>
    ${list.map(t => `<div class="item"><b>${esc(t.title)}</b><br><small>👤 ${esc(t.assignee_name || '—')} · <span class="late-txt">📅 ${esc(t.due_date)}</span></small></div>`).join('') || '<p>Чунин вазифа нест 🎉</p>'}
    <div class="actions"><button class="ghost" onclick="closeModal()">Пӯшидан</button></div>`);
}

/* ---------- Фурмаи вазифаи нав ---------- */
function taskForm() {
  const b = S.board;
  openModal(`<h3 style="margin-top:0">Вазифаи нав</h3>
    <label>Ном</label><input id="nT"><label>Тавсиф</label><textarea id="nD"></textarea>
    <div class="row"><div><label>Иҷрокунанда</label><select id="nA"><option value="">—</option>${b.members.map(m => `<option value="${m.id}">${esc(m.name)}</option>`).join('')}</select></div>
    <div><label>Сутун</label><select id="nC">${b.columns.map(c => `<option value="${c.id}">${esc(c.title)}</option>`).join('')}</select></div>
    <div><label>Мӯҳлат</label><input type="date" id="nDue"></div>
    <div><label>Афзалият</label><select id="nP"><option value="low">Паст</option><option value="medium" selected>Миёна</option><option value="high">Баланд</option></select></div></div>
    <div class="err" id="nE"></div>
    <div class="actions"><button class="ghost" onclick="closeModal()">Бекор</button><button id="nGo">Сохтан</button></div>`);
  $('#nGo').onclick = async () => {
    try {
      await api('task_create', {project_id: S.pid, title: $('#nT').value, description: $('#nD').value, assignee_id: $('#nA').value,
        column_id: $('#nC').value, due_date: $('#nDue').value, priority: $('#nP').value});
      closeModal(); loadBoard();
    } catch (e) { $('#nE').textContent = e.message; }
  };
}

/* ---------- Корти вазифа: тафсилот, шарҳ, замима, таърих ---------- */
async function openTask(id) {
  let d;
  try { d = await api('task_detail', {id}); } catch (e) { return alert(e.message); }
  const t = d.task, mgr = S.board.role === 'manager', dis = mgr ? '' : 'disabled';
  openModal(`<h3 style="margin-top:0">${esc(t.title)}</h3>
    <label>Ном</label><input id="eT" value="${esc(t.title)}" ${dis}>
    <label>Тавсиф</label><textarea id="eD" ${dis}>${esc(t.description)}</textarea>
    <div class="row"><div><label>Иҷрокунанда</label><select id="eA" ${dis}><option value="">—</option>${S.board.members.map(m => `<option value="${m.id}" ${t.assignee_id == m.id ? 'selected' : ''}>${esc(m.name)}</option>`).join('')}</select></div>
    <div><label>Мӯҳлат</label><input type="date" id="eDue" value="${t.due_date || ''}" ${dis}></div>
    <div><label>Афзалият</label><select id="eP" ${dis}>${Object.entries(PR).map(([k, v]) => `<option value="${k}" ${t.priority === k ? 'selected' : ''}>${v}</option>`).join('')}</select></div></div>
    <div class="err" id="eE"></div>
    ${mgr ? '<div class="actions"><button id="eSave">Захира кардан</button></div>' : ''}

    <div class="sec"><h4>💬 Шарҳҳо</h4>
      ${d.comments.map(c => `<div class="item"><b>${esc(c.name)}</b> <small>${esc(c.created_at)}</small><br>${esc(c.body)}</div>`).join('') || '<small>Шарҳ нест</small>'}
      <textarea id="cB" placeholder="Шарҳ нависед..." style="margin-top:8px"></textarea>
      <button id="cGo" style="margin-top:6px">Фиристодан</button></div>

    <div class="sec"><h4>📎 Замимаҳо</h4>
      ${d.files.map(f => `<div class="item"><a href="uploads/${esc(f.stored_name)}" target="_blank">${esc(f.original_name)}</a> <small>${esc(f.created_at)}</small></div>`).join('') || '<small>Замима нест</small>'}
      <input type="file" id="fl" style="margin-top:8px"></div>

    <div class="sec"><h4>🕓 Таърихи тағйирот</h4>
      ${d.history.map(h => `<div class="item"><b>${esc(h.name)}</b> <small>${esc(h.created_at)}</small><br>${esc(h.details)}</div>`).join('')}</div>
    <div class="actions"><button class="ghost" onclick="closeModal()">Пӯшидан</button></div>`);

  const again = () => openTask(id);
  if (mgr) $('#eSave').onclick = async () => {
    try {
      await api('task_update', {id, title: $('#eT').value, description: $('#eD').value, assignee_id: $('#eA').value, due_date: $('#eDue').value, priority: $('#eP').value});
      await loadBoard(); again();
    } catch (e) { $('#eE').textContent = e.message; }
  };
  $('#cGo').onclick = async () => { try { await api('comment_add', {task_id: id, body: $('#cB').value}); again(); } catch (e) { alert(e.message); } };
  $('#fl').onchange = async ev => {
    const fd = new FormData(); fd.append('task_id', id); fd.append('file', ev.target.files[0]);
    try { await api('upload', fd); again(); } catch (e) { alert(e.message); }
  };
}

init();
