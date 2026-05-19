/**
 * JSON API helper — kiosk calls use no credentials; supervisor calls use cookies.
 */
async function tmApi(action, payload = {}, withCredentials = false) {
  const url = 'api.php?action=' + encodeURIComponent(action);
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: withCredentials ? 'same-origin' : 'omit',
    body: JSON.stringify({ action, ...payload }),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok && !data.error) {
    throw new Error(res.statusText || 'Request failed');
  }
  return data;
}

function tmFormatDt(iso) {
  if (!iso) return '—';
  try {
    const d = new Date(iso.replace(' ', 'T'));
    return d.toLocaleString();
  } catch {
    return iso;
  }
}

/** Supervisor only — multipart upload. Returns { ok, path } */
async function tmUploadToolImage(file) {
  const fd = new FormData();
  fd.append('image', file);
  const res = await fetch('api.php?action=upload_tool_image', {
    method: 'POST',
    credentials: 'same-origin',
    body: fd,
  });
  return res.json();
}

/** Super admin only — import tools from CSV/XLSX */
async function tmUploadToolsImport(file, companyId, assetType) {
  const fd = new FormData();
  fd.append('file', file);
  fd.append('company_id', String(companyId));
  fd.append('asset_type', assetType);
  const res = await fetch('api.php?action=tools_import', {
    method: 'POST',
    credentials: 'same-origin',
    body: fd,
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok && !data.error) {
    throw new Error(res.statusText || 'Import failed');
  }
  return data;
}
