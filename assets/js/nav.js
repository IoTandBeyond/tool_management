/** Call after DOM ready on pages that include includes/nav.php */
function tmInitNav() {
  tmApi('me', {}, true).then(function (d) {
    if (!d.logged_in) return;
    var role = d.role;
    var el = function (id) { return document.getElementById(id); };
    if (role === 'super_admin') {
      if (el('nav-companies')) el('nav-companies').style.display = '';
    }
    if (role === 'super_admin' || role === 'admin') {
      if (el('nav-warehouses')) el('nav-warehouses').style.display = '';
    }
    if (role === 'super_admin' || role === 'admin') {
      if (el('nav-users')) el('nav-users').style.display = '';
    }
  }).catch(function () {});
}
