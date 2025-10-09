(() => {
  const appEl = document.getElementById('app');
  const toasts = [];
  let toastTimer = null;
  let lineChart = null;
  let pieChart = null;

  const state = {
    user: null,
    csrfToken: null,
    overview: { total_clicks: 0, today_clicks: 0, active_links: 0 },
    lineDays: 7,
    lineData: [],
    pieData: [],
    links: [],
    linkTotal: 0,
    linkPage: 1,
    linkSize: 10,
    linkSearch: '',
    drawerOpen: false,
    selectedLink: null,
    targets: [],
    recentClicks: [],
    clickTotal: 0,
    clickPage: 1,
    clickSize: 15,
    clickFilters: { slug: '', date_from: '', date_to: '', utm_source: '', utm_content: '' },
  };

  function showToast(message, type = 'success') {
    toasts.push({ id: Date.now(), message, type });
    renderToasts();
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      toasts.shift();
      renderToasts();
    }, 2500);
  }

  function renderToasts() {
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }
    container.innerHTML = toasts
      .map(
        (t) => `<div class="toast ${t.type}">${escapeHtml(t.message)}</div>`
      )
      .join('');
  }

  function escapeHtml(str) {
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  async function api(action, { method = 'GET', body = null, params = null } = {}) {
    const url = new URL(window.location.href);
    url.search = '';
    url.searchParams.set('action', action);
    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
          url.searchParams.set(key, value);
        }
      });
    }

    const options = {
      method,
      headers: {},
      credentials: 'same-origin',
    };

    if (method !== 'GET') {
      let formData;
      if (body instanceof FormData) {
        formData = body;
      } else {
        formData = new FormData();
        if (body && typeof body === 'object') {
          Object.entries(body).forEach(([key, value]) => {
            if (value !== undefined && value !== null) {
              formData.append(key, value);
            }
          });
        }
      }
      options.body = formData;
      if (state.csrfToken && action !== 'login') {
        options.headers['X-CSRF-Token'] = state.csrfToken;
      }
    }

    const response = await fetch(url.toString(), options);
    if (response.status === 401) {
      state.user = null;
      state.csrfToken = null;
      render();
      throw new Error('UNAUTHORIZED');
    }
    if (response.status === 403) {
      throw new Error('BAD_CSRF');
    }
    let data = null;
    try {
      data = await response.json();
    } catch (error) {
      throw new Error('网络错误');
    }
    if (!response.ok || !data.ok) {
      const message = data?.msg || '请求失败';
      throw new Error(message);
    }
    return data;
  }

  async function init() {
    try {
      const me = await api('me');
      state.user = me.data.user;
      state.csrfToken = me.data.csrf_token;
      await loadInitialData();
    } catch (error) {
      state.user = null;
      state.csrfToken = null;
    }
    render();
  }

  async function loadInitialData() {
    await Promise.all([loadOverview(), loadLineData(), loadPieData(), loadLinks(), loadRecentClicks()]);
  }

  async function loadOverview() {
    const res = await api('stats.overview');
    state.overview = res.data;
  }

  async function loadLineData() {
    const res = await api('stats.by_day', { params: { days: state.lineDays } });
    state.lineData = res.data;
    renderCharts();
  }

  async function loadPieData(linkId = null) {
    const params = {};
    if (linkId) params.link_id = linkId;
    const res = await api('stats.by_target', { params });
    state.pieData = res.data || [];
    renderCharts();
  }

  async function loadLinks() {
    const params = { page: state.linkPage, size: state.linkSize };
    if (state.linkSearch) params.q = state.linkSearch;
    const res = await api('link.list', { params });
    state.links = res.data.items;
    state.linkTotal = res.data.total;
  }

  async function loadTargets(linkId) {
    const res = await api('target.list', { params: { link_id: linkId } });
    state.targets = res.data;
  }

  async function loadRecentClicks() {
    const params = { page: state.clickPage, size: state.clickSize, ...state.clickFilters };
    const res = await api('stats.recent_clicks', { params });
    state.recentClicks = res.data.items;
    state.clickTotal = res.data.total;
  }

  function render() {
    if (!state.user) {
      renderLogin();
    } else {
      renderDashboard();
      renderToasts();
      renderCharts();
    }
  }

  function renderLogin() {
    appEl.innerHTML = `
      <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200">
        <div class="card max-w-md w-full">
          <h1 class="text-2xl font-semibold mb-6 text-slate-900 text-center">ShortLink 登录</h1>
          <form id="login-form" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-slate-600 mb-1">用户名</label>
              <input required name="username" type="text" class="input" placeholder="请输入用户名">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-600 mb-1">密码</label>
              <input required name="password" type="password" class="input" placeholder="请输入密码">
            </div>
            <button type="submit" class="btn w-full">登录</button>
          </form>
        </div>
      </div>
    `;
    document.getElementById('login-form').addEventListener('submit', handleLogin);
  }

  function renderDashboard() {
    const totalPages = Math.max(1, Math.ceil(state.linkTotal / state.linkSize));
    const clickPages = Math.max(1, Math.ceil(state.clickTotal / state.clickSize));
    const drawerClass = state.drawerOpen ? 'drawer open' : 'drawer';
    appEl.innerHTML = `
      <div class="min-h-screen">
        <header class="bg-white shadow-sm">
          <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
            <div>
              <h1 class="text-2xl font-semibold text-slate-900">ShortLink Dashboard</h1>
              <p class="text-sm text-slate-500">欢迎回来，${escapeHtml(state.user.username)}</p>
            </div>
            <div class="flex items-center gap-3">
              <button id="export-btn" class="btn secondary">导出 CSV</button>
              <button id="logout-btn" class="btn">退出登录</button>
            </div>
          </div>
        </header>
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
          <section class="grid gap-4 md:grid-cols-3">
            ${renderKpiCard('Total Clicks', state.overview.total_clicks)}
            ${renderKpiCard('Today Clicks', state.overview.today_clicks)}
            ${renderKpiCard('Active Links', state.overview.active_links)}
          </section>
          <section class="grid gap-6 lg:grid-cols-2">
            <div class="card">
              <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-slate-800">近${state.lineDays}日访问趋势</h2>
                <div class="flex gap-2">
                  <button data-days="7" class="btn secondary px-3 py-1 text-sm ${state.lineDays === 7 ? 'ring-2 ring-indigo-500' : ''}">7天</button>
                  <button data-days="30" class="btn secondary px-3 py-1 text-sm ${state.lineDays === 30 ? 'ring-2 ring-indigo-500' : ''}">30天</button>
                </div>
              </div>
              <canvas id="line-chart" height="260"></canvas>
            </div>
            <div class="card">
              <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-slate-800">目标占比</h2>
                <select id="pie-filter" class="select max-w-xs">
                  <option value="">全站</option>
                  ${state.links
                    .map((link) => `<option value="${link.id}" ${state.selectedLink && state.selectedLink.id === link.id ? 'selected' : ''}>${escapeHtml(link.slug)}</option>`)
                    .join('')}
                </select>
              </div>
              <canvas id="pie-chart" height="260"></canvas>
            </div>
          </section>
          <section class="card">
            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
              <div>
                <h2 class="text-lg font-semibold text-slate-800">短链列表</h2>
                <p class="text-sm text-slate-500">管理短链与分流目标</p>
              </div>
              <div class="flex gap-2">
                <input id="link-search" value="${escapeHtml(state.linkSearch)}" class="input" placeholder="搜索 slug 或标题">
                <button id="create-link-btn" class="btn">新增短链</button>
              </div>
            </div>
            <div class="table-scroll">
              <table class="table">
                <thead>
                  <tr>
                    <th>Slug</th>
                    <th>标题</th>
                    <th>状态</th>
                    <th>总点击</th>
                    <th>今日点击</th>
                    <th>创建时间</th>
                    <th>操作</th>
                  </tr>
                </thead>
                <tbody>
                  ${state.links
                    .map((link) => `
                      <tr>
                        <td class="font-mono text-sm">${escapeHtml(link.slug)}</td>
                        <td>${escapeHtml(link.title || '-')}</td>
                        <td>${renderStatus(link.is_active)}</td>
                        <td>${link.clicks_total}</td>
                        <td>${link.clicks_today}</td>
                        <td>${formatDateTime(link.created_at)}</td>
                        <td>
                          <div class="flex flex-wrap gap-2">
                            <button class="btn secondary px-3 py-1 text-xs" data-action="open" data-id="${link.id}">详情</button>
                            <button class="btn secondary px-3 py-1 text-xs" data-action="toggle" data-id="${link.id}" data-active="${link.is_active}">${link.is_active ? '停用' : '启用'}</button>
                            <button class="btn secondary px-3 py-1 text-xs" data-action="delete" data-id="${link.id}">删除</button>
                            <button class="btn secondary px-3 py-1 text-xs" data-action="copy" data-slug="${escapeHtml(link.slug)}">复制链接</button>
                          </div>
                        </td>
                      </tr>
                    `)
                    .join('') || '<tr><td colspan="7" class="text-center text-slate-400 py-6">暂无数据</td></tr>'}
                </tbody>
              </table>
            </div>
            <div class="flex justify-between items-center mt-4 text-sm text-slate-500">
              <div>共 ${state.linkTotal} 条记录</div>
              <div class="flex gap-2">
                <button class="btn secondary px-3 py-1" data-page="prev" ${state.linkPage <= 1 ? 'disabled' : ''}>上一页</button>
                <span>第 ${state.linkPage} / ${totalPages} 页</span>
                <button class="btn secondary px-3 py-1" data-page="next" ${state.linkPage >= totalPages ? 'disabled' : ''}>下一页</button>
              </div>
            </div>
          </section>
          <section class="card">
            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
              <div>
                <h2 class="text-lg font-semibold text-slate-800">访问明细</h2>
                <p class="text-sm text-slate-500">支持筛选 UTM / 时间范围</p>
              </div>
              <div class="flex flex-wrap gap-2">
                ${renderClickFilters()}
                <button id="click-filter-btn" class="btn secondary">筛选</button>
                <button id="click-reset-btn" class="btn secondary">重置</button>
              </div>
            </div>
            <div class="table-scroll">
              <table class="table">
                <thead>
                  <tr>
                    <th>时间</th>
                    <th>Slug</th>
                    <th>目标</th>
                    <th>IP</th>
                    <th>UTM</th>
                    <th>Referrer</th>
                    <th>UA</th>
                  </tr>
                </thead>
                <tbody>
                  ${state.recentClicks
                    .map((row) => `
                      <tr>
                        <td>${formatDateTime(row.created_at)}</td>
                        <td class="font-mono text-sm">${escapeHtml(row.slug)}</td>
                        <td>${row.target_id || '-'}</td>
                        <td>${escapeHtml(row.ip || '')}</td>
                        <td>${renderUtm(row)}</td>
                        <td class="truncate max-w-[200px]" title="${escapeHtml(row.referrer || '')}">${escapeHtml(row.referrer || '')}</td>
                        <td>
                          <button class="btn secondary px-3 py-1 text-xs" data-ua="${encodeURIComponent(row.ua || '')}">查看</button>
                        </td>
                      </tr>
                    `)
                    .join('') || '<tr><td colspan="7" class="text-center text-slate-400 py-6">暂无数据</td></tr>'}
                </tbody>
              </table>
            </div>
            <div class="flex justify-between items-center mt-4 text-sm text-slate-500">
              <div>共 ${state.clickTotal} 条记录</div>
              <div class="flex gap-2">
                <button class="btn secondary px-3 py-1" data-click-page="prev" ${state.clickPage <= 1 ? 'disabled' : ''}>上一页</button>
                <span>第 ${state.clickPage} / ${clickPages} 页</span>
                <button class="btn secondary px-3 py-1" data-click-page="next" ${state.clickPage >= clickPages ? 'disabled' : ''}>下一页</button>
              </div>
            </div>
          </section>
        </main>
      </div>
      <div class="${drawerClass}" id="link-drawer">
        <div class="p-5 border-b border-slate-200 flex items-center justify-between">
          <div>
            <h3 class="text-lg font-semibold text-slate-800">短链详情</h3>
            <p class="text-sm text-slate-500">管理短链属性与分流目标</p>
          </div>
          <button id="drawer-close" class="btn secondary px-3 py-1 text-xs">关闭</button>
        </div>
        <div class="flex-1 overflow-y-auto p-5 space-y-6">
          ${state.selectedLink ? renderDrawerContent() : '<p class="text-sm text-slate-500">请选择短链</p>'}
        </div>
      </div>
    `;

    document.getElementById('logout-btn').addEventListener('click', handleLogout);
    document.getElementById('export-btn').addEventListener('click', handleExport);
    appEl.querySelectorAll('button[data-days]').forEach((btn) => {
      btn.addEventListener('click', async (event) => {
        state.lineDays = Number(event.currentTarget.dataset.days);
        await loadLineData();
        render();
      });
    });
    document.getElementById('pie-filter').addEventListener('change', async (event) => {
      const linkId = event.target.value ? Number(event.target.value) : null;
      if (linkId) {
        const link = state.links.find((l) => l.id === linkId);
        state.selectedLink = link || null;
      } else {
        state.selectedLink = null;
      }
      await loadPieData(linkId);
      render();
    });
    document.getElementById('link-search').addEventListener('change', async (event) => {
      state.linkSearch = event.target.value.trim();
      state.linkPage = 1;
      await loadLinks();
      render();
    });
    document.getElementById('create-link-btn').addEventListener('click', openCreateLink);
    appEl.querySelectorAll('button[data-action]').forEach((btn) => {
      const action = btn.dataset.action;
      if (action === 'open') {
        btn.addEventListener('click', () => openLinkDrawer(Number(btn.dataset.id)));
      }
      if (action === 'toggle') {
        btn.addEventListener('click', () => toggleLink(Number(btn.dataset.id), Number(btn.dataset.active)));
      }
      if (action === 'delete') {
        btn.addEventListener('click', () => deleteLink(Number(btn.dataset.id)));
      }
      if (action === 'copy') {
        btn.addEventListener('click', () => copyLink(btn.dataset.slug));
      }
    });
    document.querySelectorAll('button[data-page]').forEach((btn) => {
      btn.addEventListener('click', async (event) => {
        const type = event.currentTarget.dataset.page;
        const totalPages = Math.max(1, Math.ceil(state.linkTotal / state.linkSize));
        if (type === 'prev' && state.linkPage > 1) {
          state.linkPage--;
        } else if (type === 'next' && state.linkPage < totalPages) {
          state.linkPage++;
        }
        await loadLinks();
        render();
      });
    });
    document.querySelectorAll('button[data-click-page]').forEach((btn) => {
      btn.addEventListener('click', async (event) => {
        const type = event.currentTarget.dataset.clickPage;
        const totalPages = Math.max(1, Math.ceil(state.clickTotal / state.clickSize));
        if (type === 'prev' && state.clickPage > 1) {
          state.clickPage--;
        } else if (type === 'next' && state.clickPage < totalPages) {
          state.clickPage++;
        }
        await loadRecentClicks();
        render();
      });
    });
    document.getElementById('click-filter-btn').addEventListener('click', async () => {
      const form = document.getElementById('click-filter-form');
      const formData = new FormData(form);
      state.clickFilters = Object.fromEntries(formData.entries());
      state.clickPage = 1;
      await loadRecentClicks();
      render();
    });
    document.getElementById('click-reset-btn').addEventListener('click', async () => {
      state.clickFilters = { slug: '', date_from: '', date_to: '', utm_source: '', utm_content: '' };
      state.clickPage = 1;
      await loadRecentClicks();
      render();
    });
    document.querySelectorAll('button[data-ua]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const ua = decodeURIComponent(btn.dataset.ua || '');
        alert(ua || '无');
      });
    });
    const drawerClose = document.getElementById('drawer-close');
    if (drawerClose) {
      drawerClose.addEventListener('click', closeDrawer);
    }
  }

  function renderKpiCard(title, value) {
    return `
      <div class="card">
        <p class="text-sm text-slate-500">${escapeHtml(title)}</p>
        <p class="mt-2 text-3xl font-semibold text-slate-900">${Number(value || 0).toLocaleString()}</p>
      </div>
    `;
  }

  function renderStatus(active) {
    return `<span class="badge ${active ? '' : 'off'}">${active ? '启用' : '停用'}</span>`;
  }

  function formatDateTime(str) {
    if (!str) return '-';
    const d = new Date(str.replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) {
      return str;
    }
    return d.toLocaleString();
  }

  function renderUtm(row) {
    const fields = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
    const list = fields
      .filter((f) => row[f])
      .map((f) => `${f.split('_')[1] || f}: ${escapeHtml(row[f])}`);
    return list.join('<br>');
  }

  function renderClickFilters() {
    const { slug, date_from, date_to, utm_source, utm_content } = state.clickFilters;
    return `
      <form id="click-filter-form" class="flex flex-wrap gap-2">
        <input name="slug" value="${escapeHtml(slug)}" class="input w-32" placeholder="Slug">
        <input type="date" name="date_from" value="${escapeHtml(date_from)}" class="input">
        <input type="date" name="date_to" value="${escapeHtml(date_to)}" class="input">
        <input name="utm_source" value="${escapeHtml(utm_source)}" class="input w-36" placeholder="utm_source">
        <input name="utm_content" value="${escapeHtml(utm_content)}" class="input w-36" placeholder="utm_content">
      </form>
    `;
  }

  function renderDrawerContent() {
    const link = state.selectedLink;
    if (!link) return '';
    return `
      <div class="space-y-4">
        <div>
          <label class="block text-sm text-slate-500 mb-1">Slug</label>
          <input type="text" class="input" value="${escapeHtml(link.slug)}" disabled>
        </div>
        <form id="link-edit-form" class="space-y-4">
          <div>
            <label class="block text-sm text-slate-500 mb-1">标题</label>
            <input name="title" class="input" value="${escapeHtml(link.title || '')}">
          </div>
          <div>
            <label class="block text-sm text-slate-500 mb-1">默认 URL</label>
            <input name="default_url" class="input" value="${escapeHtml(link.default_url)}">
          </div>
          <button class="btn w-full" type="submit">保存</button>
        </form>
        <div class="border-t border-slate-200 pt-4">
          <h4 class="text-md font-semibold text-slate-800 mb-3">目标列表</h4>
          <div id="target-list" class="space-y-3">
            ${state.targets
              .map(
                (target) => `
                  <div class="border border-slate-200 rounded-lg p-3 space-y-2">
                    <div class="text-xs text-slate-500">ID: ${target.id}</div>
                    <div class="text-sm break-words">${escapeHtml(target.target_url)}</div>
                    <div class="flex items-center justify-between">
                      <span class="badge badge-weight">权重 ${target.weight}</span>
                      <span class="badge ${target.is_active ? '' : 'off'}">${target.is_active ? '启用' : '停用'}</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                      <button class="btn secondary px-3 py-1 text-xs" data-target="toggle" data-id="${target.id}" data-active="${target.is_active}">${target.is_active ? '停用' : '启用'}</button>
                      <button class="btn secondary px-3 py-1 text-xs" data-target="edit" data-id="${target.id}">编辑</button>
                      <button class="btn secondary px-3 py-1 text-xs" data-target="delete" data-id="${target.id}">删除</button>
                    </div>
                  </div>
                `
              )
              .join('') || '<p class="text-sm text-slate-400">暂无目标</p>'}
          </div>
          <form id="target-create-form" class="space-y-3 mt-4">
            <div>
              <label class="block text-sm text-slate-500 mb-1">目标 URL</label>
              <input required name="target_url" class="input" placeholder="https://example.com">
            </div>
            <div>
              <label class="block text-sm text-slate-500 mb-1">权重</label>
              <input required name="weight" type="number" min="0" class="input" value="1">
            </div>
            <button class="btn w-full" type="submit">新增目标</button>
          </form>
        </div>
      </div>
    `;
  }

  async function handleLogin(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const data = new FormData(form);
    try {
      const res = await api('login', { method: 'POST', body: data });
      state.user = res.data.user;
      state.csrfToken = res.data.csrf_token;
      await loadInitialData();
      showToast('登录成功', 'success');
      render();
    } catch (error) {
      showToast('用户名或密码错误', 'error');
    }
  }

  async function handleLogout() {
    try {
      await api('logout', { method: 'POST' });
    } catch (error) {
      // ignore
    }
    state.user = null;
    state.csrfToken = null;
    state.drawerOpen = false;
    render();
  }

  async function openCreateLink() {
    const title = prompt('请输入短链标题(可选)');
    const defaultUrl = prompt('请输入默认 URL (必填, http/https)');
    if (!defaultUrl) {
      return;
    }
    const slug = prompt('自定义短码(可选, 3-20 位, 留空自动生成)') || '';
    if (slug && !/^[-_a-zA-Z0-9]{3,20}$/.test(slug)) {
      showToast('短码不符合要求', 'error');
      return;
    }
    if (!/^https?:\/\//i.test(defaultUrl)) {
      showToast('URL 需以 http(s) 开头', 'error');
      return;
    }
    try {
      await api('link.create', { method: 'POST', body: { title: title || '', default_url: defaultUrl, slug } });
      await loadLinks();
      await loadPieData(state.selectedLink ? state.selectedLink.id : null);
      showToast('创建成功', 'success');
      render();
    } catch (error) {
      showToast(error.message, 'error');
    }
  }

  async function openLinkDrawer(id) {
    const link = state.links.find((l) => l.id === id);
    if (!link) return;
    state.selectedLink = link;
    state.drawerOpen = true;
    await loadTargets(link.id);
    await loadPieData(link.id);
    render();
    bindDrawerEvents();
  }

  function closeDrawer() {
    state.drawerOpen = false;
    state.selectedLink = null;
    render();
  }

  function bindDrawerEvents() {
    const form = document.getElementById('link-edit-form');
    if (form) {
      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(form);
        const defaultUrl = formData.get('default_url');
        if (!/^https?:\/\//i.test(defaultUrl)) {
          showToast('URL 需以 http(s) 开头', 'error');
          return;
        }
        try {
          await api('link.update', { method: 'POST', body: { id: state.selectedLink.id, title: formData.get('title'), default_url: defaultUrl } });
          await loadLinks();
          const updated = state.links.find((l) => l.id === state.selectedLink.id);
          state.selectedLink = updated;
          showToast('保存成功', 'success');
          render();
        } catch (error) {
          showToast(error.message, 'error');
        }
      });
    }
    const targetCreate = document.getElementById('target-create-form');
    if (targetCreate) {
      targetCreate.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(targetCreate);
        const weight = Number(formData.get('weight'));
        const url = formData.get('target_url');
        if (Number.isNaN(weight) || weight < 0) {
          showToast('权重需大于等于 0', 'error');
          return;
        }
        if (!/^https?:\/\//i.test(url)) {
          showToast('URL 需以 http(s) 开头', 'error');
          return;
        }
        try {
          await api('target.add', { method: 'POST', body: { link_id: state.selectedLink.id, target_url: url, weight } });
          await loadTargets(state.selectedLink.id);
          await loadPieData(state.selectedLink.id);
          showToast('添加成功', 'success');
          render();
        } catch (error) {
          showToast(error.message, 'error');
        }
      });
    }
    document.querySelectorAll('button[data-target]').forEach((btn) => {
      const action = btn.dataset.target;
      const id = Number(btn.dataset.id);
      if (action === 'toggle') {
        btn.addEventListener('click', async () => {
          const active = Number(btn.dataset.active);
          await targetToggle(id, active);
        });
      }
      if (action === 'delete') {
        btn.addEventListener('click', async () => {
          if (confirm('确认删除该目标吗？')) {
            await targetDelete(id);
          }
        });
      }
      if (action === 'edit') {
        btn.addEventListener('click', async () => {
          await targetEdit(id);
        });
      }
    });
  }

  async function toggleLink(id, isActive) {
    try {
      await api('link.toggle', { method: 'POST', body: { id, is_active: isActive ? 0 : 1 } });
      await loadLinks();
      showToast('操作成功', 'success');
      render();
    } catch (error) {
      showToast(error.message, 'error');
    }
  }

  async function deleteLink(id) {
    if (!confirm('确认删除该短链吗？')) return;
    try {
      await api('link.delete', { method: 'POST', body: { id } });
      await loadLinks();
      await loadPieData(state.selectedLink ? state.selectedLink.id : null);
      if (state.selectedLink && state.selectedLink.id === id) {
        closeDrawer();
      }
      showToast('删除成功', 'success');
      render();
    } catch (error) {
      showToast(error.message, 'error');
    }
  }

  async function copyLink(slug) {
    const base = window.location.origin.replace(/\/$/, '');
    const url = `${base}/${slug}`;
    try {
      await navigator.clipboard.writeText(url);
      showToast('已复制到剪贴板', 'success');
    } catch (error) {
      showToast('复制失败', 'error');
    }
  }

  async function targetToggle(id, isActive) {
    try {
      await api('target.toggle', { method: 'POST', body: { id, is_active: isActive ? 0 : 1 } });
      await loadTargets(state.selectedLink.id);
      await loadPieData(state.selectedLink.id);
      showToast('操作成功', 'success');
      render();
    } catch (error) {
      showToast(error.message, 'error');
    }
  }

  async function targetDelete(id) {
    try {
      await api('target.delete', { method: 'POST', body: { id } });
      await loadTargets(state.selectedLink.id);
      await loadPieData(state.selectedLink.id);
      showToast('删除成功', 'success');
      render();
    } catch (error) {
      showToast(error.message, 'error');
    }
  }

  async function targetEdit(id) {
    const target = state.targets.find((t) => t.id === id);
    if (!target) return;
    const url = prompt('目标 URL', target.target_url);
    if (!url) return;
    if (!/^https?:\/\//i.test(url)) {
      showToast('URL 需以 http(s) 开头', 'error');
      return;
    }
    const weight = prompt('权重', target.weight);
    const weightNum = Number(weight);
    if (Number.isNaN(weightNum) || weightNum < 0) {
      showToast('权重需大于等于 0', 'error');
      return;
    }
    try {
      await api('target.update', { method: 'POST', body: { id, target_url: url, weight: weightNum } });
      await loadTargets(state.selectedLink.id);
      await loadPieData(state.selectedLink.id);
      showToast('更新成功', 'success');
      render();
    } catch (error) {
      showToast(error.message, 'error');
    }
  }

  function handleExport() {
    const params = new URLSearchParams();
    Object.entries(state.clickFilters).forEach(([key, value]) => {
      if (value) params.set(key, value);
    });
    const url = `${window.location.pathname}?action=export.csv${params.toString() ? `&${params.toString()}` : ''}`;
    window.open(url, '_blank');
  }

  function renderCharts() {
    if (!state.user) return;
    const lineCanvas = document.getElementById('line-chart');
    if (lineCanvas && window.Chart) {
      const labels = state.lineData.map((item) => item.day);
      const data = state.lineData.map((item) => item.total);
      if (lineChart) {
        lineChart.data.labels = labels;
        lineChart.data.datasets[0].data = data;
        lineChart.update();
      } else {
        lineChart = new Chart(lineCanvas.getContext('2d'), {
          type: 'line',
          data: {
            labels,
            datasets: [
              {
                label: 'Clicks',
                data,
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99,102,241,0.15)',
                fill: true,
                tension: 0.4,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              y: { beginAtZero: true },
            },
          },
        });
      }
    }

    const pieCanvas = document.getElementById('pie-chart');
    if (pieCanvas && window.Chart) {
      const labels = state.pieData.map((item) => item.target_url || `ID ${item.id}`);
      const data = state.pieData.map((item) => Number(item.total || 0));
      if (pieChart) {
        pieChart.data.labels = labels;
        pieChart.data.datasets[0].data = data;
        pieChart.update();
      } else {
        pieChart = new Chart(pieCanvas.getContext('2d'), {
          type: 'doughnut',
          data: {
            labels,
            datasets: [
              {
                data,
                backgroundColor: ['#6366f1', '#22d3ee', '#f97316', '#14b8a6', '#f43f5e', '#8b5cf6'],
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                position: 'bottom',
              },
            },
          },
        });
      }
    }
  }

  window.addEventListener('focus', () => {
    if (state.user) {
      loadOverview().then(render);
    }
  });

  init();
})();
