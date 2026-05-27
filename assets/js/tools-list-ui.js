/**
 * Client-side search, category filter, and pagination for tools / measurement tables.
 */
function tmToolsListView(config) {
  var state = {
    page: 1,
    pageSize: 15,
    search: '',
    categoryFilter: ''
  };

  function getItems() {
    return config.getItems() || [];
  }

  function matchesSearch(t, q) {
    if (!q) return true;
    var parts = [
      t.name,
      t.barcode,
      t.nfc_id,
      t.description,
      t.category_name,
      t.warehouse_name,
      t.brand_model,
      t.location,
      t.uom,
      t.range_spec,
      t.tool_condition
    ];
    var hay = parts
      .filter(function (p) { return p != null && String(p).trim() !== ''; })
      .join(' ')
      .toLowerCase();
    return hay.indexOf(q) !== -1;
  }

  function matchesCategory(t) {
    var cat = state.categoryFilter;
    if (cat === '') return true;
    if (cat === '__none__') {
      return t.category_id == null || t.category_id === '' || Number(t.category_id) === 0;
    }
    return String(t.category_id || '') === String(cat);
  }

  function getFiltered() {
    var q = state.search.trim().toLowerCase();
    return getItems().filter(function (t) {
      return matchesCategory(t) && matchesSearch(t, q);
    });
  }

  function render() {
    var tbody = config.elements.tbody;
    if (!tbody) return;

    var filtered = getFiltered();
    var total = filtered.length;
    var size = state.pageSize;
    var pages = total > 0 ? Math.ceil(total / size) : 1;
    if (state.page > pages) state.page = pages;
    if (state.page < 1) state.page = 1;

    var start = (state.page - 1) * size;
    var slice = filtered.slice(start, start + size);

    tbody.innerHTML = '';
    if (!total) {
      var colSpan = config.emptyColSpan || 8;
      var tr = document.createElement('tr');
      var emptyMsg = typeof tmT === 'function' ? tmT('common.no_items_match') : 'No items match your search or filter.';
      tr.innerHTML = '<td colspan="' + colSpan + '" class="sub" style="text-align:center;padding:1.5rem;">' + emptyMsg + '</td>';
      tbody.appendChild(tr);
    } else {
      config.renderRows(slice, tbody);
    }

    var el = config.elements;
    if (el.meta) {
      el.meta.textContent = total
        ? (typeof tmT === 'function'
          ? tmT('common.showing', { from: start + 1, to: Math.min(start + size, total), total: total })
          : 'Showing ' + (start + 1) + '–' + Math.min(start + size, total) + ' of ' + total + ' item(s)')
        : (typeof tmT === 'function' ? tmT('common.no_items') : 'No items to display');
    }
    if (el.pageInfo) {
      el.pageInfo.textContent = total
        ? (typeof tmT === 'function' ? tmT('common.page_of', { page: state.page, pages: pages }) : 'Page ' + state.page + ' of ' + pages)
        : (typeof tmT === 'function' ? tmT('common.page_of', { page: 1, pages: 1 }) : 'Page 1 of 1');
    }
    if (el.btnPrev) el.btnPrev.disabled = state.page <= 1;
    if (el.btnNext) el.btnNext.disabled = state.page >= pages;

    if (typeof config.afterRender === 'function') {
      config.afterRender(slice);
    }
  }

  function wire() {
    var el = config.elements;
    if (el.search) {
      el.search.addEventListener('input', function () {
        state.search = el.search.value;
        state.page = 1;
        render();
      });
    }
    if (el.category) {
      el.category.addEventListener('change', function () {
        state.categoryFilter = el.category.value;
        state.page = 1;
        render();
      });
    }
    if (el.pageSize) {
      el.pageSize.addEventListener('change', function () {
        state.pageSize = parseInt(el.pageSize.value, 10) || 15;
        state.page = 1;
        render();
      });
    }
    if (el.btnPrev) {
      el.btnPrev.addEventListener('click', function () {
        if (state.page > 1) {
          state.page--;
          render();
        }
      });
    }
    if (el.btnNext) {
      el.btnNext.addEventListener('click', function () {
        var filtered = getFiltered();
        var pages = filtered.length > 0 ? Math.ceil(filtered.length / state.pageSize) : 1;
        if (state.page < pages) {
          state.page++;
          render();
        }
      });
    }
  }

  function populateCategories(categories) {
    var sel = config.elements.category;
    if (!sel) return;
    var cur = sel.value;
    sel.innerHTML = '';
    var all = document.createElement('option');
    all.value = '';
    all.textContent = typeof tmT === 'function' ? tmT('common.all_categories') : 'All categories';
    sel.appendChild(all);
    var none = document.createElement('option');
    none.value = '__none__';
    none.textContent = typeof tmT === 'function' ? tmT('common.uncategorized') : 'Uncategorized';
    sel.appendChild(none);
    (categories || []).forEach(function (c) {
      var o = document.createElement('option');
      o.value = c.id;
      o.textContent = c.name;
      sel.appendChild(o);
    });
    if (cur) sel.value = cur;
  }

  return {
    render: render,
    wire: wire,
    populateCategories: populateCategories,
    resetPage: function () {
      state.page = 1;
    }
  };
}

/**
 * Stock quantity for a tool at a warehouse (from assignments[] or manager list stock_qty).
 */
function tmStockQtyForWarehouse(tool, warehouseId) {
  if (!tool) return null;
  var wid = warehouseId ? Number(warehouseId) : 0;
  var assignments = tool.assignments || [];
  if (assignments.length) {
    if (wid) {
      for (var i = 0; i < assignments.length; i++) {
        if (Number(assignments[i].warehouse_id) === wid) {
          return assignments[i].stock_qty;
        }
      }
    }
    return assignments[0].stock_qty;
  }
  if (tool.stock_qty !== undefined && tool.stock_qty !== null) {
    return tool.stock_qty;
  }
  return null;
}

/** Default warehouse select value when opening edit. */
function tmDefaultEditWarehouseId(tool, managerWarehouseId) {
  if (!tool) return managerWarehouseId ? String(managerWarehouseId) : '';
  if (tool.stock_qty != null && managerWarehouseId) {
    return String(managerWarehouseId);
  }
  var a = tool.assignments || [];
  if (a.length) return String(a[0].warehouse_id);
  if (managerWarehouseId) return String(managerWarehouseId);
  return '';
}

/** Fill stock input from tool + selected warehouse. */
function tmApplyEditStockFields(tool, warehouseSelect, stockInput) {
  if (!tool || !stockInput) return;
  var whId = warehouseSelect ? warehouseSelect.value : '';
  var sq = tmStockQtyForWarehouse(tool, whId);
  stockInput.value = sq != null && sq !== '' ? String(sq) : '';
}
