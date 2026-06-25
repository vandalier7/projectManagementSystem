<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Tasks — ProjectDashboard Dev Tool</title>
<style>
  :root {
    --bg: #f5f4f0;
    --panel: #ffffff;
    --border: #ddd9cf;
    --border-strong: #b9b4a6;
    --text: #2c2c2a;
    --text-secondary: #6b6a64;
    --text-tertiary: #97958c;
    --accent: #185fa5;
    --accent-bg: #e6f1fb;
    --green: #3b6d11;
    --green-bg: #eaf3de;
    --red: #a32d2d;
    --red-bg: #fcebeb;
    --amber: #854f0b;
    --amber-bg: #faeeda;
    --radius: 10px;
    --radius-sm: 6px;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: var(--bg);
    color: var(--text);
    padding: 24px;
  }
  .topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1100px;
    margin: 0 auto 20px;
  }
  .topbar h1 { font-size: 18px; margin: 0; }
  .acting-as {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
  }
  select, input[type=text], textarea {
    font-family: inherit;
    font-size: 13px;
    border: 1px solid var(--border-strong);
    border-radius: var(--radius-sm);
    padding: 6px 10px;
    background: #fff;
    color: var(--text);
  }
  textarea { width: 100%; resize: vertical; min-height: 50px; }
  button {
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    border: 1px solid var(--border-strong);
    background: #fff;
    color: var(--text);
    padding: 7px 14px;
    border-radius: var(--radius-sm);
    cursor: pointer;
  }
  button:hover { background: #f7f5f0; }
  button.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
  button.primary:hover { background: #14517e; }
  button.success { background: var(--green-bg); border-color: #b9d690; color: var(--green); }
  button.danger { background: var(--red-bg); border-color: #efb6b6; color: var(--red); }
  button.small { padding: 4px 10px; font-size: 12px; }

  .layout { max-width: 1100px; margin: 0 auto; display: grid; gap: 16px; }
  .panel { background: var(--panel); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px; }
  .panel h2 { font-size: 14px; margin: 0 0 14px; }

  .create-form { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 8px; align-items: end; margin-bottom: 4px; }
  .create-form label { font-size: 11px; color: var(--text-secondary); display: block; margin-bottom: 3px; }
  .create-form > div { display: flex; flex-direction: column; }

  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th { text-align: left; padding: 8px 10px; color: var(--text-secondary); font-weight: 600; font-size: 11px; text-transform: uppercase; border-bottom: 1px solid var(--border); }
  td { padding: 10px; border-bottom: 1px solid var(--border); vertical-align: top; }
  tr:hover { background: #fafaf7; }

  .status-pill { font-size: 11px; font-weight: 600; padding: 2px 9px; border-radius: 20px; white-space: nowrap; }
  .status-todo { background: #f0ebe0; color: #6b5e3f; }
  .status-in_progress { background: var(--accent-bg); color: var(--accent); }
  .status-submitted { background: var(--amber-bg); color: var(--amber); }
  .status-done { background: var(--green-bg); color: var(--green); }
  .status-closed { background: var(--red-bg); color: var(--red); }

  .chain-tag { font-size: 10px; color: var(--text-tertiary); border: 1px solid var(--border); border-radius: 4px; padding: 1px 5px; margin-left: 6px; }
  .actions { display: flex; gap: 6px; flex-wrap: wrap; }
  .meta { font-size: 11px; color: var(--text-tertiary); margin-top: 2px; }

  .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.35); display: flex; align-items: center; justify-content: center; z-index: 50; }
  .modal { background: #fff; border-radius: var(--radius); padding: 20px; width: 420px; }
  .modal h3 { margin: 0 0 12px; font-size: 15px; }
  .modal .field { margin-bottom: 10px; }
  .modal .field label { font-size: 12px; font-weight: 600; color: var(--text-secondary); display: block; margin-bottom: 4px; }
  .modal .field input, .modal .field textarea { width: 100%; }
  .modal-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 14px; }

  .toast { position: fixed; bottom: 20px; right: 20px; background: var(--text); color: #fff; padding: 10px 16px; border-radius: var(--radius-sm); font-size: 13px; opacity: 0; transition: opacity 0.2s; }
  .toast.show { opacity: 1; }
  .toast.error { background: var(--red); }

  .empty { text-align: center; padding: 30px; color: var(--text-tertiary); font-size: 13px; }

  .capabilities { font-size: 12px; color: var(--text-secondary); display: flex; flex-wrap: wrap; gap: 6px 14px; margin-top: 4px; }
  .cap-yes { color: var(--green); }
  .cap-no { color: var(--text-tertiary); text-decoration: line-through; }
  .role-tag { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 20px; }
  .role-tag.admin { background: var(--accent-bg); color: var(--accent); }
  .role-tag.lead { background: var(--green-bg); color: var(--green); }
  .role-tag.member { background: #f0ebe0; color: #6b5e3f; }
</style>
</head>
<body>

<div class="topbar">
  <h1>Tasks <span style="color:var(--text-tertiary); font-weight:400;">— dev tool</span></h1>
  <div class="acting-as">
    <span>Acting as:</span>
    <select id="actingAsSelect"></select>
  </div>
</div>

<div class="layout" style="margin-bottom: 16px;">
  <div class="panel" id="capabilitiesPanel" style="padding: 12px 18px;">
    <div id="capabilitiesContent"></div>
  </div>
</div>

<div class="layout">

  <div class="panel" id="createPanel">
    <h2>Create task</h2>
    <div class="create-form">
      <div>
        <label>Title</label>
        <input type="text" id="newTitle" placeholder="Task title">
      </div>
      <div>
        <label>Project</label>
        <select id="newProjectId"></select>
      </div>
      <div>
        <label>Assign to</label>
        <select id="newAssignedTo"></select>
      </div>
      <div>
        <label>Requires submission</label>
        <select id="newRequiresSubmission">
          <option value="">(use project default)</option>
          <option value="true">Yes</option>
          <option value="false">No</option>
        </select>
      </div>
      <div>
        <button class="primary" id="createTaskBtn">Create</button>
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>All tasks</h2>
    <table>
      <thead>
        <tr>
          <th>Task</th>
          <th>Assigned</th>
          <th>Status</th>
          <th>Submission</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="taskTableBody"></tbody>
    </table>
    <div id="emptyState" class="empty" style="display:none;">No tasks yet.</div>
  </div>

</div>

<div id="toast" class="toast"></div>
<div id="modalRoot"></div>

<script>
const API = '/api';
let users = [];
let projects = [];
let tasks = [];

function actingAsId() {
  return Number(document.getElementById('actingAsSelect').value);
}

// --- Permission mirrors -----------------------------------------------
// These mirror app/Models/User.php, Project.php, Task.php. The PHP
// versions are the actual source of truth enforced by the server — these
// exist only so the UI can show/hide controls accurately. If the PHP
// logic changes, update these to match.

function isAdmin(user) {
  return user?.system_role === 'admin';
}

function isLedBy(project, user) {
  return project?.lead_id === user?.id;
}

function isAssignedTo(task, user) {
  return task.assigned_to === user?.id;
}

function canBeReviewedBy(task, user, project) {
  return isLedBy(project, user) || isAdmin(user);
}

function canBeEditedBy(task, user, project) {
  return isLedBy(project, user) || isAdmin(user);
}

function canBeSubmittedBy(task, user) {
  return isAssignedTo(task, user);
}

function canBeSelfCompletedBy(task, user, project) {
  if (!isAssignedTo(task, user)) return false;
  return isLedBy(project, user) || !task.requires_submission;
}

function isLeadOnAnyProject(user) {
  return projects.some(p => p.lead_id === user?.id);
}

function renderCapabilitiesPanel() {
  const acting = actingAsId();
  const user = users.find(u => u.id === acting);
  const content = document.getElementById('capabilitiesContent');
  if (!user) { content.innerHTML = ''; return; }

  const leadProjects = projects.filter(p => p.lead_id === user.id);
  const roleTag = isAdmin(user)
    ? '<span class="role-tag admin">Admin</span>'
    : leadProjects.length > 0
      ? '<span class="role-tag lead">Lead on ' + leadProjects.length + ' project(s)</span>'
      : '<span class="role-tag member">Member</span>';

  const caps = [];
  caps.push([isAdmin(user) || isLeadOnAnyProject(user), 'Create tasks']);
  caps.push([isAdmin(user) || isLeadOnAnyProject(user), 'Edit/delete any task on a project they lead']);
  caps.push([isAdmin(user), 'Review submissions on ANY project (not just one they lead)']);
  caps.push([!isAdmin(user), 'Submit/take back their own assigned tasks']);
  caps.push([true, 'View all tasks (read access is project-wide for everyone)']);

  content.innerHTML = `
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
      <strong>${escapeHtml(user.full_name)}</strong> ${roleTag}
    </div>
    <div class="capabilities">
      ${caps.map(([allowed, label]) => `<span class="${allowed ? 'cap-yes' : 'cap-no'}">${allowed ? '✓' : '✗'} ${label}</span>`).join('')}
    </div>
  `;
}

function taskCanManage(task) {
  const acting = actingAsId();
  const user = users.find(u => u.id === acting);
  const project = projects.find(p => p.id === task.project_id);
  return canBeEditedBy(task, user, project);
}

function userName(id) {
  const u = users.find(u => u.id === id);
  return u ? u.full_name : '—';
}

function showToast(message, isError = false) {
  const toast = document.getElementById('toast');
  toast.textContent = message;
  toast.className = 'toast show' + (isError ? ' error' : '');
  setTimeout(() => toast.className = 'toast', 2500);
}

async function api(path, options = {}) {
  const res = await fetch(`${API}${path}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(options.headers || {}),
    },
  });
  const data = await res.json().catch(() => null);
  if (!res.ok) {
    throw new Error(data?.message || `Request failed (${res.status})`);
  }
  return data;
}

async function loadAll() {
  [users, projects, tasks] = await Promise.all([
    api('/users'),
    api('/projects'),
    api('/tasks'),
  ]);
  renderActingAsOptions();
  renderProjectOptions();
  renderAssigneeOptions();
  renderTasks();
  updateCreatePanelVisibility();
  renderCapabilitiesPanel();
}

function renderActingAsOptions() {
  const select = document.getElementById('actingAsSelect');
  const previousValue = select.value;
  select.innerHTML = users.map(u =>
    `<option value="${u.id}">${escapeHtml(u.full_name)} (${u.system_role}, #${u.id})</option>`
  ).join('');
  if (previousValue && users.some(u => String(u.id) === previousValue)) {
    select.value = previousValue;
  }
}

function renderProjectOptions() {
  const select = document.getElementById('newProjectId');
  select.innerHTML = projects.map(p =>
    `<option value="${p.id}">${escapeHtml(p.name)}</option>`
  ).join('');
}

function renderAssigneeOptions() {
  const select = document.getElementById('newAssignedTo');
  select.innerHTML = '<option value="">Unassigned</option>' + users.map(u =>
    `<option value="${u.id}">${escapeHtml(u.full_name)}</option>`
  ).join('');
}

function statusLabel(s) {
  return { todo: 'To do', in_progress: 'In progress', submitted: 'Submitted', done: 'Done', closed: 'Closed' }[s] || s;
}

function renderTasks() {
  const tbody = document.getElementById('taskTableBody');
  const emptyState = document.getElementById('emptyState');

  const visible = tasks.filter(t => t.status !== 'closed').sort((a, b) => b.id - a.id);

  if (visible.length === 0) {
    tbody.innerHTML = '';
    emptyState.style.display = 'block';
    return;
  }
  emptyState.style.display = 'none';

  tbody.innerHTML = visible.map(t => `
    <tr>
      <td>
        <strong>${escapeHtml(t.title)}</strong>
        ${t.previous_task_id ? `<span class="chain-tag">revision of #${t.previous_task_id}</span>` : ''}
        <div class="meta">#${t.id} · ${escapeHtml(t.project?.name || '')}</div>
      </td>
      <td>${escapeHtml(userName(t.assigned_to))}</td>
      <td><span class="status-pill status-${t.status}">${statusLabel(t.status)}</span></td>
      <td>${t.submission_link ? `<a href="${escapeHtml(t.submission_link)}" target="_blank" style="font-size:12px;">link</a>` : '<span class="meta">—</span>'}</td>
      <td><div class="actions">${renderActions(t)}</div></td>
    </tr>
  `).join('');

  tbody.querySelectorAll('[data-action]').forEach(btn => {
    btn.addEventListener('click', () => handleAction(btn.dataset.action, Number(btn.dataset.taskId)));
  });
}

function renderActions(t) {
  const acting = actingAsId();
  const canManage = taskCanManage(t);
  let html = '';

  if (t.status === 'todo' && t.assigned_to === acting) {
    html += `<button class="small primary" data-action="start" data-task-id="${t.id}">Start</button>`;
  }
  if (t.status === 'in_progress' && t.assigned_to === acting) {
    html += `<button class="small primary" data-action="submit" data-task-id="${t.id}">Submit / Complete</button>`;
  }
  if (t.status === 'submitted' && t.assigned_to === acting) {
    html += `<button class="small" data-action="takeback" data-task-id="${t.id}">Take back</button>`;
  }
  if (t.status === 'submitted' && canManage) {
    html += `<button class="small success" data-action="approve" data-task-id="${t.id}">Approve</button>`;
    html += `<button class="small danger" data-action="reject" data-task-id="${t.id}">Reject</button>`;
  }
  if (canManage) {
    html += `<button class="small" data-action="edit" data-task-id="${t.id}">Edit</button>`;
    html += `<button class="small danger" data-action="delete" data-task-id="${t.id}">Delete</button>`;
  }
  if (!html) {
    html = '<span class="meta">—</span>';
  }

  return html;
}

function updateCreatePanelVisibility() {
  const acting = actingAsId();
  const user = users.find(u => u.id === acting);
  document.getElementById('createPanel').style.display = (isAdmin(user) || isLeadOnAnyProject(user)) ? 'block' : 'none';
}

async function handleAction(action, taskId) {
  const task = tasks.find(t => t.id === taskId);
  const acting = actingAsId();

  try {
    if (action === 'start') {
      await api(`/tasks/${taskId}`, { method: 'PUT', body: JSON.stringify({ acting_as_user_id: acting, status: 'in_progress' }) });
      showToast('Task started.');
      await loadAll();
    }

    else if (action === 'submit') {
      if (task.requires_submission) {
        openSubmitModal(task);
        return;
      } else {
        await api(`/tasks/${taskId}`, { method: 'PUT', body: JSON.stringify({ acting_as_user_id: acting, status: 'done' }) });
        showToast('Task completed.');
        await loadAll();
      }
    }

    else if (action === 'takeback') {
      await api(`/tasks/${taskId}`, { method: 'PUT', body: JSON.stringify({ acting_as_user_id: acting, status: 'in_progress' }) });
      showToast('Submission taken back.');
      await loadAll();
    }

    else if (action === 'approve') {
      await api(`/tasks/${taskId}`, { method: 'PUT', body: JSON.stringify({ acting_as_user_id: acting, status: 'done' }) });
      showToast('Task approved.');
      await loadAll();
    }

    else if (action === 'reject') {
      openRejectModal(task);
      return;
    }

    else if (action === 'edit') {
      openEditModal(task);
      return;
    }

    else if (action === 'delete') {
      if (!confirm(`Delete "${task.title}"? This cannot be undone.`)) return;
      await api(`/tasks/${taskId}`, { method: 'DELETE' });
      showToast('Task deleted.');
      await loadAll();
    }
  } catch (err) {
    showToast(err.message, true);
  }
}

function closeModal() {
  document.getElementById('modalRoot').innerHTML = '';
}

function openSubmitModal(task) {
  document.getElementById('modalRoot').innerHTML = `
    <div class="modal-overlay">
      <div class="modal">
        <h3>Submit "${escapeHtml(task.title)}"</h3>
        <div class="field">
          <label>Submission link</label>
          <input type="text" id="modalSubmissionLink" placeholder="https://github.com/...">
        </div>
        <div class="field">
          <label>Notes</label>
          <textarea id="modalSubmissionNotes" placeholder="What should the reviewer check"></textarea>
        </div>
        <div class="modal-actions">
          <button id="modalCancel">Cancel</button>
          <button class="primary" id="modalConfirm">Submit</button>
        </div>
      </div>
    </div>
  `;
  document.getElementById('modalCancel').addEventListener('click', closeModal);
  document.getElementById('modalConfirm').addEventListener('click', async () => {
    const link = document.getElementById('modalSubmissionLink').value.trim();
    const notes = document.getElementById('modalSubmissionNotes').value.trim();
    if (!link) { showToast('Submission link is required.', true); return; }
    try {
      await api(`/tasks/${task.id}`, {
        method: 'PUT',
        body: JSON.stringify({ acting_as_user_id: actingAsId(), status: 'submitted', submission_link: link, submission_notes: notes }),
      });
      showToast('Task submitted.');
      closeModal();
      await loadAll();
    } catch (err) {
      showToast(err.message, true);
    }
  });
}

function openRejectModal(task) {
  document.getElementById('modalRoot').innerHTML = `
    <div class="modal-overlay">
      <div class="modal">
        <h3>Reject "${escapeHtml(task.title)}"</h3>
        <div class="field">
          <label>Feedback for revision</label>
          <textarea id="modalReviewComment" placeholder="What needs to change"></textarea>
        </div>
        <div class="field">
          <label>New task title</label>
          <input type="text" id="modalNewTitle" value="${escapeHtml(task.title)} (revised)">
        </div>
        <div class="field">
          <label>Reassign to</label>
          <select id="modalNewAssignee"></select>
        </div>
        <div class="modal-actions">
          <button id="modalCancel">Cancel</button>
          <button class="danger" id="modalConfirm">Reject & create revision</button>
        </div>
      </div>
    </div>
  `;
  document.getElementById('modalNewAssignee').innerHTML = users.map(u =>
    `<option value="${u.id}" ${u.id === task.assigned_to ? 'selected' : ''}>${escapeHtml(u.full_name)}</option>`
  ).join('');
  document.getElementById('modalCancel').addEventListener('click', closeModal);
  document.getElementById('modalConfirm').addEventListener('click', async () => {
    const comment = document.getElementById('modalReviewComment').value.trim();
    const newTitle = document.getElementById('modalNewTitle').value.trim();
    const newAssignee = Number(document.getElementById('modalNewAssignee').value);
    try {
      await api(`/tasks/${task.id}`, {
        method: 'PUT',
        body: JSON.stringify({
          acting_as_user_id: actingAsId(),
          status: 'closed',
          review_comment: comment || 'No comment provided.',
          new_title: newTitle,
          new_assigned_to: newAssignee,
        }),
      });
      showToast('Task rejected, revision created.');
      closeModal();
      await loadAll();
    } catch (err) {
      showToast(err.message, true);
    }
  });
}

function openEditModal(task) {
  document.getElementById('modalRoot').innerHTML = `
    <div class="modal-overlay">
      <div class="modal">
        <h3>Edit "${escapeHtml(task.title)}"</h3>
        <div class="field">
          <label>Title</label>
          <input type="text" id="modalEditTitle" value="${escapeHtml(task.title)}">
        </div>
        <div class="field">
          <label>Description</label>
          <textarea id="modalEditDescription">${escapeHtml(task.description || '')}</textarea>
        </div>
        <div class="field">
          <label>Assign to</label>
          <select id="modalEditAssignee"></select>
        </div>
        <div class="modal-actions">
          <button id="modalCancel">Cancel</button>
          <button class="primary" id="modalConfirm">Save</button>
        </div>
      </div>
    </div>
  `;
  document.getElementById('modalEditAssignee').innerHTML = users.map(u =>
    `<option value="${u.id}" ${u.id === task.assigned_to ? 'selected' : ''}>${escapeHtml(u.full_name)}</option>`
  ).join('');
  document.getElementById('modalCancel').addEventListener('click', closeModal);
  document.getElementById('modalConfirm').addEventListener('click', async () => {
    try {
      await api(`/tasks/${task.id}`, {
        method: 'PUT',
        body: JSON.stringify({
          acting_as_user_id: actingAsId(),
          title: document.getElementById('modalEditTitle').value.trim(),
          description: document.getElementById('modalEditDescription').value.trim(),
          assigned_to: Number(document.getElementById('modalEditAssignee').value),
        }),
      });
      showToast('Task updated.');
      closeModal();
      await loadAll();
    } catch (err) {
      showToast(err.message, true);
    }
  });
}

document.getElementById('createTaskBtn').addEventListener('click', async () => {
  const title = document.getElementById('newTitle').value.trim();
  if (!title) { showToast('Title is required.', true); return; }

  const requiresVal = document.getElementById('newRequiresSubmission').value;
  const body = {
    title,
    project_id: Number(document.getElementById('newProjectId').value),
    assigned_to: document.getElementById('newAssignedTo').value || null,
  };
  if (requiresVal !== '') body.requires_submission = requiresVal === 'true';

  try {
    await api('/tasks', { method: 'POST', body: JSON.stringify(body) });
    document.getElementById('newTitle').value = '';
    showToast('Task created.');
    await loadAll();
  } catch (err) {
    showToast(err.message, true);
  }
});

function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  const div = document.createElement('div');
  div.textContent = String(str);
  return div.innerHTML;
}

document.getElementById('actingAsSelect').addEventListener('change', () => {
  renderTasks();
  updateCreatePanelVisibility();
  renderCapabilitiesPanel();
});

loadAll().catch(err => showToast('Failed to load: ' + err.message, true));
</script>

</body>
</html>