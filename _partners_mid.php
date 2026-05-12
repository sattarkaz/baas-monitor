
<!-- ═══════════════════ DRILL-DOWN MODAL ═══════════════════════════════════ -->
<div class="modal-overlay" id="modalOverlay" onclick="if(event.target===this)closeModal()">
  <div class="modal" style="max-width:880px">
    <div class="modal-header">
      <h2 id="modalTitle">Partner Detail</h2>
      <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body" id="modalBody">
      <div style="text-align:center;color:#94a3b8;padding:40px 0">
        <i class="fa-solid fa-circle-notch fa-spin" style="font-size:24px"></i>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════ ADD / EDIT PARTNER MODAL ═══════════════════════════ -->
<div id="partnerFormModal" class="modal-overlay" style="display:none">
  <div class="modal-box" style="max-width:700px">
    <div class="modal-header">
      <h2 id="pfTitle"><i class="fa-solid fa-handshake"></i> Tərəfdaş əlavə et</h2>
      <button class="modal-close" onclick="closePartnerForm()">×</button>
    </div>
    <div class="modal-body">
      <form id="partnerForm" onsubmit="savePartner(event)">
        <input type="hidden" id="pf_partner_id">

        <div class="form-section-label"><i class="fa-solid fa-building"></i> Şirkət məlumatları</div>
        <div class="form-row">
          <div class="form-group" style="flex:2">
            <label>Şirkətin adı *</label>
            <input type="text" id="pf_partner_name" class="form-input" placeholder="AzərTech MMC" required>
          </div>
          <div class="form-group">
            <label>Hüquqi forma *</label>
            <select id="pf_legal_form" class="form-input" required>
              <option value="MMC">MMC</option><option value="ASC">ASC</option>
              <option value="SC">SC</option><option value="QSC">QSC</option>
              <option value="Filial">Filial</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>VÖEN *</label>
            <input type="text" id="pf_voen" class="form-input" placeholder="1234567890" maxlength="10" required>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select id="pf_status" class="form-input">
              <option value="active">Active</option><option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Hüquqi ünvan *</label>
          <input type="text" id="pf_legal_address" class="form-input" placeholder="Bakı ş., Nəsimi r., Füzuli küç. 12, AZ1000" required>
        </div>

        <div class="form-section-label" style="margin-top:14px"><i class="fa-solid fa-signature"></i> İmzalayan tərəf</div>
        <div class="form-row">
          <div class="form-group" style="flex:2">
            <label>Ad Soyad *</label>
            <input type="text" id="pf_signatory_name" class="form-input" placeholder="Rəşad Məmmədov" required>
          </div>
          <div class="form-group">
            <label>Vəzifə *</label>
            <input type="text" id="pf_signatory_position" class="form-input" placeholder="Direktor" required>
          </div>
        </div>

        <div class="form-section-label" style="margin-top:14px"><i class="fa-solid fa-university"></i> Bank rekvizitləri</div>
        <div class="form-row">
          <div class="form-group">
            <label>Bank adı *</label>
            <input type="text" id="pf_bank_name" class="form-input" placeholder="ABB Bank" required>
          </div>
          <div class="form-group">
            <label>Bank kodu (MFO)</label>
            <input type="text" id="pf_bank_code" class="form-input" placeholder="505044">
          </div>
        </div>
        <div class="form-group">
          <label>Bank hesabı (IBAN) *</label>
          <input type="text" id="pf_bank_account" class="form-input" placeholder="AZ21NABZ00000000137010001944" required>
        </div>

        <div class="form-section-label" style="margin-top:14px"><i class="fa-solid fa-address-card"></i> Əlaqə məlumatları</div>
        <div class="form-row">
          <div class="form-group">
            <label>Email</label>
            <input type="email" id="pf_contact_email" class="form-input" placeholder="contact@company.az">
          </div>
          <div class="form-group">
            <label>Telefon</label>
            <input type="text" id="pf_contact_phone" class="form-input" placeholder="+994 12 555 00 00">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Müqavilə tarixi</label>
            <input type="text" id="pf_contract_date" class="form-input" placeholder="dd.mm.yyyy">
          </div>
          <div class="form-group">
            <label>Məsul menecer</label>
            <input type="text" id="pf_account_manager" class="form-input" placeholder="Aynur Həsənova">
          </div>
        </div>

        <div class="modal-actions" style="margin-top:20px">
          <button type="button" class="btn btn-outline" onclick="closePartnerForm()">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Yadda saxla</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
.form-section-label {
  font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;
  letter-spacing:.5px;margin-bottom:8px;padding-bottom:4px;
  border-bottom:1px solid #e2e8f0;
}
</style>

<script>
// ── Data stores ───────────────────────────────────────────────────────────────
let PARTNERS = <?= json_encode(array_values($MOCK_PARTNERS)) ?>;
let PACKAGES = <?= json_encode(array_values($MOCK_PACKAGES)) ?>;
const SUMMARY = <?= json_encode($MOCK_PARTNER_SUMMARY) ?>;
let PARTNER_DOCS = <?= json_encode($MOCK_PARTNER_DOCS) ?>;
let _nextPid  = <?= max(array_column($MOCK_PARTNERS,'partner_id')) + 1 ?>;
let _nextDocId = 100;
let _editingPid = null;

// ── Formatters ────────────────────────────────────────────────────────────────
function status_badge(s) {
  const map = {
    active:    ['Active',    '#e8f5e9','#2e7d32'],
    inactive:  ['Inactive',  '#fce4ec','#c62828'],
    exhausted: ['Exhausted', '#fff3e0','#e65100'],
    closed:    ['Closed',    '#f3e5f5','#6a1b9a'],
  };
  const [label, bg, color] = map[s] || [s,'#f1f5f9','#334155'];
  return `<span style="background:${bg};color:${color};padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600">${label}</span>`;
}

function usage_bar_html(pct) {
  const color = pct >= 90 ? 'linear-gradient(90deg,#e53935,#ef5350)'
              : pct >= 70 ? 'linear-gradient(90deg,#f57c00,#ffb74d)'
              :             'linear-gradient(90deg,#43a047,#66bb6a)';
  return `<div style="height:6px;background:#e2e8f0;border-radius:4px;overflow:hidden">
            <div style="width:${Math.min(100,pct)}%;height:100%;background:${color};border-radius:4px"></div>
          </div><small style="color:#64748b;font-size:11px">${pct}%</small>`;
}

function fmt_azn(n) {
  if (n >= 1e6) return (n/1e6).toFixed(2) + ' mln ₼';
  if (n >= 1e3) return (n/1e3).toFixed(1) + ' min ₼';
  return n.toFixed(2) + ' ₼';
}

function fmt_bytes(b) {
  if (b >= 1048576) return (b/1048576).toFixed(1) + ' MB';
  if (b >= 1024)    return (b/1024).toFixed(0) + ' KB';
  return b + ' B';
}

function fileIcon(type) {
  const icons  = {pdf:'fa-file-pdf',docx:'fa-file-word',doc:'fa-file-word',adoc:'fa-file-lines',odt:'fa-file-lines'};
  const colors = {pdf:'#e53935',docx:'#1e88e5',doc:'#1e88e5',adoc:'#f57c00',odt:'#f57c00'};
  return `<i class="fa-solid ${icons[type]||'fa-file'}" style="color:${colors[type]||'#64748b'};font-size:18px"></i>`;
}

// ── Drill-down modal ──────────────────────────────────────────────────────────
function openDrilldown(pid) {
  const partner = PARTNERS.find(p => p.partner_id == pid);
  const pkgs    = PACKAGES.filter(pk => pk.partner_id == pid);
  const s       = SUMMARY[pid] || {};
  const docs    = PARTNER_DOCS[pid] || [];
  if (!partner) return;

  document.getElementById('modalTitle').innerHTML =
    `${partner.partner_name} <span style="font-size:13px;font-weight:400;color:#94a3b8">${partner.legal_form} · VÖEN: ${partner.voen||'—'}</span>`;

  let html = '';

  // ── Partner details ──
  html += `<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px">
    <div style="background:#f8fafc;border-radius:8px;padding:12px;border-left:3px solid #1e88e5">
      <div style="font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;margin-bottom:6px">Şirkət məlumatları</div>
      <div style="font-size:12px;line-height:2">
        <div><span style="color:#94a3b8">VÖEN:</span> <strong>${partner.voen||'—'}</strong></div>
        <div><span style="color:#94a3b8">Hüquqi ünvan:</span> ${partner.legal_address||'—'}</div>
        <div><span style="color:#94a3b8">Email:</span> ${partner.contact_email||'—'}</div>
        <div><span style="color:#94a3b8">Telefon:</span> ${partner.contact_phone||'—'}</div>
        <div><span style="color:#94a3b8">Müqavilə:</span> ${partner.contract_date} · ${partner.account_manager||'—'}</div>
      </div>
    </div>
    <div style="background:#f8fafc;border-radius:8px;padding:12px;border-left:3px solid #8e24aa">
      <div style="font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;margin-bottom:6px">Bank rekvizitləri</div>
      <div style="font-size:12px;line-height:2">
        <div><span style="color:#94a3b8">Bank:</span> <strong>${partner.bank_name||'—'}</strong></div>
        <div><span style="color:#94a3b8">IBAN:</span> <span style="font-family:monospace;font-size:11px">${partner.bank_account||'—'}</span></div>
        <div><span style="color:#94a3b8">MFO kodu:</span> ${partner.bank_code||'—'}</div>
        <div><span style="color:#94a3b8">İmzalayan:</span> <strong>${partner.signatory_name||'—'}</strong></div>
        <div><span style="color:#94a3b8">Vəzifə:</span> ${partner.signatory_position||'—'}</div>
      </div>
    </div>
  </div>`;

  // ── Export buttons ──
  html += `<div style="margin-bottom:16px;padding:12px;background:#f0f7ff;border-radius:8px;border:1px solid #bbdefb">
    <div style="font-size:11px;font-weight:700;color:#1565c0;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">
      <i class="fa-solid fa-file-export"></i> Sənəd şablonlarını yüklə (.docx)
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="generate_doc.php?partner_id=${pid}&type=contract" target="_blank"
         class="btn btn-outline" style="font-size:12px;padding:7px 14px;color:#1e3a5f;border-color:#1565c0;background:#fff">
        <i class="fa-solid fa-file-contract"></i> Əsas Müqavilə
      </a>
      <a href="generate_doc.php?partner_id=${pid}&type=tariff" target="_blank"
         class="btn btn-outline" style="font-size:12px;padding:7px 14px;color:#1b5e20;border-color:#2e7d32;background:#fff">
        <i class="fa-solid fa-table-list"></i> Tarif Cədvəli Əlavəsi
      </a>
      <a href="generate_doc.php?partner_id=${pid}&type=nda" target="_blank"
         class="btn btn-outline" style="font-size:12px;padding:7px 14px;color:#4a148c;border-color:#8e24aa;background:#fff">
        <i class="fa-solid fa-file-shield"></i> Məxfilik Sazişi (NDA)
      </a>
    </div>
  </div>`;

  // ── KPI row ──
  html += `<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:16px">
    <div style="text-align:center;background:#e3f2fd;border-radius:8px;padding:12px">
      <div style="font-size:20px;font-weight:700;color:#1e88e5">${Number(s.total_issued||0).toLocaleString('az-AZ')}</div>
      <div style="font-size:11px;color:#1565c0">Buraxılmış kartlar</div>
    </div>
    <div style="text-align:center;background:#e8f5e9;border-radius:8px;padding:12px">
      <div style="font-size:20px;font-weight:700;color:#2e7d32">${Number(s.total_remaining||0).toLocaleString('az-AZ')}</div>
      <div style="font-size:11px;color:#1b5e20">Qalan kartlar</div>
    </div>
    <div style="text-align:center;background:#fff3e0;border-radius:8px;padding:12px">
      <div style="font-size:20px;font-weight:700;color:#e65100">${(s.avg_usage_pct||0).toFixed(1)}%</div>
      <div style="font-size:11px;color:#bf360c">Orta istifadə</div>
    </div>
  </div>`;

  // ── Card packages ──
  const pkgRows = pkgs.length ? pkgs.map(pk => `
    <tr>
      <td style="padding:7px 10px;font-family:monospace;font-size:12px">PKG-${String(pk.package_id).padStart(3,'0')}</td>
      <td style="padding:7px 10px;font-size:12px">${pk.start_date} – ${pk.end_date}</td>
      <td style="padding:7px 10px;text-align:right">${Number(pk.package_size).toLocaleString('az-AZ')}</td>
      <td style="padding:7px 10px;text-align:right">${Number(pk.issued_cards).toLocaleString('az-AZ')}</td>
      <td style="padding:7px 10px;text-align:right">${Number(pk.remaining_cards).toLocaleString('az-AZ')}</td>
      <td style="padding:7px 10px;min-width:130px">${usage_bar_html(pk.usage_percent)}</td>
      <td style="padding:7px 10px">${status_badge(pk.status)}</td>
    </tr>`).join('')
    : '<tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:16px">Kart paketi yoxdur</td></tr>';

  html += `<div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">
    <i class="fa-solid fa-layer-group"></i> Kart paketləri
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:16px">
    <thead><tr style="background:#f8fafc">
      <th style="padding:7px 10px;text-align:left;font-size:11px;color:#64748b;border-bottom:1px solid #e2e8f0">ID</th>
      <th style="padding:7px 10px;font-size:11px;color:#64748b;border-bottom:1px solid #e2e8f0">Müddət</th>
      <th style="padding:7px 10px;text-align:right;font-size:11px;color:#64748b;border-bottom:1px solid #e2e8f0">Ölçü</th>
      <th style="padding:7px 10px;text-align:right;font-size:11px;color:#64748b;border-bottom:1px solid #e2e8f0">Buraxılmış</th>
      <th style="padding:7px 10px;text-align:right;font-size:11px;color:#64748b;border-bottom:1px solid #e2e8f0">Qalıq</th>
      <th style="padding:7px 10px;min-width:130px;font-size:11px;color:#64748b;border-bottom:1px solid #e2e8f0">İstifadə</th>
      <th style="padding:7px 10px;font-size:11px;color:#64748b;border-bottom:1px solid #e2e8f0">Status</th>
    </tr></thead>
    <tbody>${pkgRows}</tbody>
  </table>`;

  // ── Transaction summary ──
  html += `<div style="background:#f8fafc;border-radius:8px;padding:12px;margin-bottom:16px">
    <div style="font-size:11px;font-weight:700;color:#64748b;margin-bottom:6px">
      <i class="fa-solid fa-chart-line"></i> Əməliyyat xülasəsi (30 gün)
    </div>
    <div style="display:flex;gap:24px;flex-wrap:wrap;font-size:13px">
      <div><span style="color:#94a3b8">Həcm:</span> <strong>${fmt_azn(s.txn_volume_30d||0)}</strong></div>
      <div><span style="color:#94a3b8">Sayı:</span> <strong>${Number(s.txn_count_30d||0).toLocaleString('az-AZ')}</strong></div>
      <div><span style="color:#94a3b8">Orta çek:</span> <strong>${s.txn_count_30d ? (s.txn_volume_30d/s.txn_count_30d).toFixed(0)+' ₼' : '—'}</strong></div>
    </div>
  </div>`;

  // ── Documents ──
  const docRows = docs.length ? docs.map(d => `
    <tr id="doc-row-${d.doc_id}">
      <td style="padding:7px 10px">${fileIcon(d.file_type)}</td>
      <td style="padding:7px 10px;font-size:13px">
        <strong>${d.file_name}</strong>
        ${d.notes ? `<div style="font-size:11px;color:#94a3b8">${d.notes}</div>` : ''}
      </td>
      <td style="padding:7px 10px;font-size:12px;color:#64748b">${fmt_bytes(d.file_size)}</td>
      <td style="padding:7px 10px;font-size:12px;color:#64748b">${d.uploaded_by}</td>
      <td style="padding:7px 10px;font-size:12px;color:#64748b">${d.uploaded_at}</td>
      <td style="padding:7px 10px">
        <button class="btn btn-outline" style="font-size:11px;padding:3px 9px;color:#e53935;border-color:#e53935"
          onclick="deleteDoc(${d.doc_id},${pid})">
          <i class="fa-solid fa-trash-can"></i>
        </button>
      </td>
    </tr>`).join('')
    : `<tr><td colspan="6" id="no-docs-row-${pid}" style="text-align:center;color:#94a3b8;padding:14px;font-size:13px">Sənəd yüklənməyib</td></tr>`;

  html += `<div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">
    <i class="fa-solid fa-folder-open"></i> Yüklənmiş sənədlər
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:10px" id="docs-table-${pid}">
    <thead><tr style="background:#f8fafc">
      <th style="padding:7px 10px;width:36px;border-bottom:1px solid #e2e8f0"></th>
      <th style="padding:7px 10px;text-align:left;font-size:11px;color:#64748b;border-bottom:1px solid #e2e8f0">Fayl adı</th>
      <th style="padding:7px 10px;font-size:11px;color:#64748b;border-bottom:1px solid #e2e8f0">Ölçü</th>
      <th style="padding:7px 10px;font-size:11px;color:#64748b;border-bottom:1px solid #e2e8f0">Yükləyən</th>
      <th style="padding:7px 10px;font-size:11px;color:#64748b;border-bottom:1px solid #e2e8f0">Tarix</th>
      <th style="padding:7px 10px;border-bottom:1px solid #e2e8f0"></th>
    </tr></thead>
    <tbody id="docs-tbody-${pid}">${docRows}</tbody>
  </table>
  <label for="doc-upload-${pid}"
    style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border:2px dashed #cbd5e1;border-radius:8px;cursor:pointer;font-size:13px;color:#64748b;transition:border-color .2s;margin-bottom:14px"
    onmouseover="this.style.borderColor='#1e88e5'" onmouseout="this.style.borderColor='#cbd5e1'">
    <i class="fa-solid fa-upload" style="color:#1e88e5"></i>
    Sənəd yüklə (PDF, DOC, DOCX, ADOC, ODT)
    <input type="file" id="doc-upload-${pid}" style="display:none"
      accept=".pdf,.doc,.docx,.adoc,.odt,.xls,.xlsx" multiple
      onchange="handleDocUpload(event,${pid})">
  </label>
  <div style="text-align:right">
    <a href="transactions.php?partner_id=${pid}" class="btn btn-primary" style="font-size:12px">
      <i class="fa-solid fa-chart-line"></i> Ətraflı Əməliyyat Analitikası
    </a>
  </div>`;

  document.getElementById('modalBody').innerHTML = html;
  document.getElementById('modalOverlay').classList.add('open');
}

function closeModal() {
  document.getElementById('modalOverlay').classList.remove('open');
}

// ── Document upload ───────────────────────────────────────────────────────────
function handleDocUpload(event, pid) {
  const files = Array.from(event.target.files);
  if (!files.length) return;
  if (!PARTNER_DOCS[pid]) PARTNER_DOCS[pid] = [];
  const now = new Date().toLocaleDateString('az-AZ').replace(/\//g,'.');
  const tbody = document.getElementById(`docs-tbody-${pid}`);

  files.forEach(file => {
    const ext = file.name.split('.').pop().toLowerCase();
    const doc = { doc_id:_nextDocId++, partner_id:pid, file_name:file.name, file_type:ext,
                  file_size:file.size, uploaded_by:'Current User', uploaded_at:now, notes:'' };
    PARTNER_DOCS[pid].push(doc);
    const noRow = document.getElementById(`no-docs-row-${pid}`);
    if (noRow) noRow.closest('tr').remove();
    const tr = document.createElement('tr');
    tr.id = `doc-row-${doc.doc_id}`;
    tr.innerHTML = `
      <td style="padding:7px 10px">${fileIcon(ext)}</td>
      <td style="padding:7px 10px;font-size:13px"><strong>${doc.file_name}</strong></td>
      <td style="padding:7px 10px;font-size:12px;color:#64748b">${fmt_bytes(doc.file_size)}</td>
      <td style="padding:7px 10px;font-size:12px;color:#64748b">${doc.uploaded_by}</td>
      <td style="padding:7px 10px;font-size:12px;color:#64748b">${doc.uploaded_at}</td>
      <td style="padding:7px 10px">
        <button class="btn btn-outline" style="font-size:11px;padding:3px 9px;color:#e53935;border-color:#e53935"
          onclick="deleteDoc(${doc.doc_id},${pid})">
          <i class="fa-solid fa-trash-can"></i>
        </button>
      </td>`;
    tbody.appendChild(tr);
  });
  showToast(`${files.length} sənəd uğurla yükləndi`);
  event.target.value = '';
}

function deleteDoc(docId, pid) {
  if (!confirm('Bu sənədi silmək istədiyinizdən əminsiniz?')) return;
  PARTNER_DOCS[pid] = (PARTNER_DOCS[pid]||[]).filter(d => d.doc_id !== docId);
  const row = document.getElementById(`doc-row-${docId}`);
  if (row) row.remove();
  showToast('Sənəd silindi');
}

// ── Partner form ──────────────────────────────────────────────────────────────
function openPartnerForm(partner = null) {
  _editingPid = partner ? partner.partner_id : null;
  document.getElementById('pfTitle').innerHTML = partner
    ? '<i class="fa-solid fa-pen-to-square"></i> Tərəfdaşı redaktə et'
    : '<i class="fa-solid fa-handshake"></i> Tərəfdaş əlavə et';
  const fields = ['partner_name','legal_form','voen','status','legal_address',
                  'signatory_name','signatory_position','bank_name','bank_code',
                  'bank_account','contact_email','contact_phone','contract_date','account_manager'];
  fields.forEach(f => {
    const el = document.getElementById('pf_' + f);
    if (el) el.value = (partner && partner[f]) ? partner[f] : '';
  });
  if (!partner) {
    document.getElementById('pf_status').value = 'active';
    document.getElementById('pf_legal_form').value = 'MMC';
  }
  document.getElementById('partnerFormModal').style.display = 'flex';
}

function closePartnerForm() {
  document.getElementById('partnerFormModal').style.display = 'none';
}

function savePartner(e) {
  e.preventDefault();
  const g = id => document.getElementById('pf_'+id)?.value?.trim() ?? '';
  const data = {
    partner_id: _editingPid || _nextPid,
    partner_name: g('partner_name'), legal_form: g('legal_form'),
    voen: g('voen'), status: g('status'), legal_address: g('legal_address'),
    signatory_name: g('signatory_name'), signatory_position: g('signatory_position'),
    bank_name: g('bank_name'), bank_code: g('bank_code'), bank_account: g('bank_account'),
    contact_email: g('contact_email'), contact_phone: g('contact_phone'),
    contract_date: g('contract_date') || new Date().toLocaleDateString('az-AZ').replace(/\//g,'.'),
    account_manager: g('account_manager'),
  };
  if (_editingPid) {
    const idx = PARTNERS.findIndex(p => p.partner_id == _editingPid);
    if (idx > -1) PARTNERS[idx] = {...PARTNERS[idx], ...data};
    showToast('Tərəfdaş yeniləndi');
  } else {
    _nextPid++;
    PARTNERS.push(data);
    showToast('Tərəfdaş əlavə edildi');
  }
  closePartnerForm();
  rebuildTable();
}

function rebuildTable() {
  const tbody = document.querySelector('#partnersTable tbody');
  if (!tbody) return;
  tbody.innerHTML = PARTNERS.map((r, i) => {
    const s = SUMMARY[r.partner_id] || {};
    const pkgs = PACKAGES.filter(pk => pk.partner_id == r.partner_id);
    const aP = pkgs.filter(pk => pk.status === 'active').length;
    const pct = s.avg_usage_pct || 0;
    const bar = `<div style="height:6px;background:#e2e8f0;border-radius:4px;overflow:hidden"><div style="width:${Math.min(100,pct)}%;height:100%;background:${pct>=90?'linear-gradient(90deg,#e53935,#ef5350)':pct>=70?'linear-gradient(90deg,#f57c00,#ffb74d)':'linear-gradient(90deg,#43a047,#66bb6a)'};border-radius:4px"></div></div><small style="color:#64748b;font-size:11px">${pct}%</small>`;
    return `<tr>
      <td><strong>${r.partner_name}</strong><div style="font-size:11px;color:#94a3b8">${r.legal_form} · ${r.contract_date}</div></td>
      <td>${status_badge(r.status)}</td>
      <td style="font-size:12px">${r.account_manager||'—'}</td>
      <td style="text-align:right">${aP} / ${pkgs.length}</td>
      <td style="text-align:right">${(s.total_pkg_size||0).toLocaleString('az-AZ')}</td>
      <td style="text-align:right">${(s.total_issued||0).toLocaleString('az-AZ')}</td>
      <td style="text-align:right">${(s.total_remaining||0).toLocaleString('az-AZ')}</td>
      <td>${bar}</td>
      <td style="text-align:right">${r.status==='active'?'<strong>'+fmt_azn(s.txn_volume_30d||0)+'</strong>':'<span style="color:#94a3b8">—</span>'}</td>
      <td style="white-space:nowrap">
        <button class="btn btn-primary" style="font-size:11px;padding:5px 11px" onclick="openDrilldown(${r.partner_id})">
          <i class="fa-solid fa-magnifying-glass-chart"></i> Detail
        </button>
        <button class="btn btn-outline" style="font-size:11px;padding:5px 11px;margin-left:4px" onclick="openPartnerForm(PARTNERS[${i}])">
          <i class="fa-solid fa-pen-to-square"></i>
        </button>
      </td>
    </tr>`;
  }).join('');
}

function showToast(msg, ok = true) {
  let t = document.getElementById('_toast');
  if (!t) { t = document.createElement('div'); t.id='_toast'; document.body.appendChild(t); }
  t.textContent = msg;
  t.style.cssText=`position:fixed;bottom:24px;right:24px;padding:12px 20px;border-radius:8px;font-size:14px;font-weight:600;z-index:9999;transition:opacity .3s;background:${ok?'#2e7d32':'#c62828'};color:#fff`;
  t.style.opacity='1';
  clearTimeout(t._t);
  t._t=setTimeout(()=>{t.style.opacity='0';},3000);
}

// Auto-open if ?id= is in URL
<?php if ($drill_id > 0): ?>
window.addEventListener('load', () => openDrilldown(<?= $drill_id ?>));
<?php endif; ?>
</script>
</html>
