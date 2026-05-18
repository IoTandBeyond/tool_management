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
    var measurementCoId = 2;
    if (d.measurement_company_id) measurementCoId = parseInt(d.measurement_company_id, 10);
    var showMeasurement = role === 'super_admin' || (d.company_id && parseInt(d.company_id, 10) === measurementCoId);
    if (showMeasurement && el('nav-measurement')) el('nav-measurement').style.display = '';
    if (role === 'super_admin' || role === 'admin') {
      if (el('nav-users')) el('nav-users').style.display = '';
    }
  }).catch(function () {});
}
