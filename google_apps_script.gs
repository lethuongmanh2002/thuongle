// Dán vào Google Sheet: Tiện ích mở rộng > Apps Script. Đổi TOKEN giống SCRIPT_TOKEN trong config.php
// Giữ nguyên SALT (đã dùng để mã hóa mật khẩu admin mẫu trong file Excel)
const TOKEN = 'DOI-MAT-KHAU-NAY', SALT = 'cd2026', TZ = 'Asia/Ho_Chi_Minh';
const COLS = {
  Kho: ['id', 'ten', 'diachi', 'ghichu'],
  Xe: ['id', 'hopdong', 'khach', 'tenxe', 'bienso', 'kho', 'ghichu', 'trangthai', 'gionhap', 'gioxuat'],
  NguoiDung: ['user', 'hash', 'ten', 'role', 'active']
};
const sh = n => SpreadsheetApp.getActive().getSheetByName(n);
const out = o => ContentService.createTextOutput(JSON.stringify(o)).setMimeType(ContentService.MimeType.JSON);
const now = () => Utilities.formatDate(new Date(), TZ, 'dd/MM/yyyy HH:mm:ss');
const hash = (u, p) => Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, u + ':' + p + ':' + SALT, Utilities.Charset.UTF_8)
  .map(b => ('0' + (b & 255).toString(16)).slice(-2)).join('');

function doGet() { return out({ok: true, data: 'Apps Script dang chay'}); }

function doPost(e) {
  const lock = LockService.getScriptLock();
  try {
    const r = JSON.parse(e.postData.contents);
    if (r.token !== TOKEN) throw new Error('Sai token');
    lock.waitLock(20000);
    const fn = {login, list, users, kho_save, kho_del, add, update, del, move, user_save, user_del}[r.action];
    if (!fn) throw new Error('Hành động không hợp lệ');
    return out({ok: true, data: fn(r)});
  } catch (err) {
    return out({ok: false, error: String(err.message || err)});
  } finally {
    try { lock.releaseLock(); } catch (x) {}
  }
}

function all(n) {
  const s = sh(n), c = COLS[n], k = s.getLastRow() - 1;
  if (k < 1) return [];
  return s.getRange(2, 1, k, c.length).getDisplayValues().filter(r => r[0]).map(r => Object.fromEntries(c.map((x, i) => [x, r[i]])));
}
function rowOf(n, key, v) {
  const s = sh(n), i = s.getRange(1, 1, s.getLastRow(), 1).getDisplayValues().flat().indexOf(v);
  if (i < 1) throw new Error('Không tìm thấy: ' + v);
  return i + 1;
}
function put(n, row, o) {
  sh(n).getRange(row, 1, 1, COLS[n].length).setNumberFormat('@').setValues([COLS[n].map(k => o[k] == null ? '' : o[k])]);
}
function nextId(n, prefix, pad) {
  const max = all(n).reduce((m, x) => Math.max(m, parseInt(x.id.slice(prefix.length)) || 0), 0);
  return prefix + String(max + 1).padStart(pad, '0');
}
const khoName = id => (all('Kho').find(k => k.id === id) || {ten: id}).ten;

function log(loai, x, nv, note) {
  const s = sh('LichSu'), row = s.getLastRow() + 1;
  s.getRange(row, 1, 1, 10).setNumberFormat('@').setValues([[now(), loai, x.id, x.hopdong, x.khach, x.tenxe, x.bienso, khoName(x.kho), nv, note || '']]);
}

function login(r) {
  const u = all('NguoiDung').find(x => x.user.toLowerCase() === String(r.user).trim().toLowerCase());
  if (!u || u.hash !== hash(u.user, r.pass)) throw new Error('Sai tên đăng nhập hoặc mật khẩu');
  if (u.active !== 'Có') throw new Error('Tài khoản đã bị khóa');
  return {user: u.user, ten: u.ten, role: u.role};
}

function list() {
  const s = sh('LichSu'), last = s.getLastRow(), from = Math.max(2, last - 499);
  const ls = last < 2 ? [] : s.getRange(from, 1, last - from + 1, 10).getDisplayValues().filter(r => r[0]).reverse();
  return {kho: all('Kho'), xe: all('Xe'), ls};
}

function kho_save(r) {
  const k = r.kho;
  if (!k.ten) throw new Error('Thiếu tên kho');
  if (k.id) put('Kho', rowOf('Kho', 'id', k.id), k);
  else { k.id = nextId('Kho', 'K', 2); put('Kho', sh('Kho').getLastRow() + 1, k); }
  return list();
}
function kho_del(r) {
  if (all('Xe').some(x => x.kho === r.id && x.trangthai === 'Trong kho')) throw new Error('Kho còn xe đang giữ, hãy xuất hoặc chuyển xe trước khi xóa');
  sh('Kho').deleteRow(rowOf('Kho', 'id', r.id));
  return list();
}

function add(r) {
  const x = r.xe;
  if (!x.hopdong || !x.khach || !x.tenxe || !x.kho) throw new Error('Thiếu mã hợp đồng, tên khách, tên xe hoặc kho');
  khoName(x.kho);
  if (all('Xe').some(v => v.hopdong.toLowerCase() === x.hopdong.toLowerCase() && v.trangthai === 'Trong kho')) throw new Error('Mã hợp đồng này đang có xe trong kho');
  x.id = nextId('Xe', 'XM', 4); x.trangthai = 'Trong kho'; x.gionhap = now(); x.gioxuat = '';
  put('Xe', sh('Xe').getLastRow() + 1, x);
  log('NHẬP', x, r.nv, x.ghichu || 'Nhập kho mới');
  return list();
}
function update(r) {
  const x = r.xe, row = rowOf('Xe', 'id', x.id), cur = all('Xe').find(v => v.id === x.id);
  x.trangthai = cur.trangthai; x.gionhap = cur.gionhap; x.gioxuat = cur.gioxuat;
  put('Xe', row, x);
  if (cur.kho !== x.kho) log('CHUYỂN', x, r.nv, 'Từ ' + khoName(cur.kho) + ' sang ' + khoName(x.kho));
  return list();
}
function del(r) { sh('Xe').deleteRow(rowOf('Xe', 'id', r.id)); return list(); }
function move(r) {
  const x = all('Xe').find(v => v.id === r.id);
  if (!x) throw new Error('Không tìm thấy xe');
  const xuat = r.loai === 'XUẤT';
  if (xuat && x.trangthai === 'Đã xuất') throw new Error('Xe này đã được xuất');
  if (!xuat && x.trangthai === 'Trong kho') throw new Error('Xe này đang ở trong kho');
  if (xuat) { x.trangthai = 'Đã xuất'; x.gioxuat = now(); } else { x.trangthai = 'Trong kho'; x.gionhap = now(); x.gioxuat = ''; }
  put('Xe', rowOf('Xe', 'id', x.id), x);
  log(r.loai, x, r.nv, r.ghichu);
  return list();
}

function users() { return all('NguoiDung').map(u => ({user: u.user, ten: u.ten, role: u.role, active: u.active})); }
function user_save(r) {
  const u = r.u, cur = all('NguoiDung').find(x => x.user.toLowerCase() === String(u.user).toLowerCase());
  if (!u.user || !u.ten) throw new Error('Thiếu tên đăng nhập hoặc họ tên');
  if (!cur && !u.pass) throw new Error('Cần đặt mật khẩu cho tài khoản mới');
  const o = {user: cur ? cur.user : u.user, ten: u.ten, role: u.role === 'admin' ? 'admin' : 'nhanvien', active: u.active === 'Không' ? 'Không' : 'Có',
    hash: u.pass ? hash(cur ? cur.user : u.user, u.pass) : cur.hash};
  put('NguoiDung', cur ? rowOf('NguoiDung', 'user', cur.user) : sh('NguoiDung').getLastRow() + 1, o);
  return users();
}
function user_del(r) { sh('NguoiDung').deleteRow(rowOf('NguoiDung', 'user', r.user)); return users(); }
