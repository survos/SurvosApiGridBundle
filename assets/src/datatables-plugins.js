import 'datatables.net-select-bs5';
import 'datatables.net-searchpanes-bs5';
import 'datatables.net-searchbuilder-bs5';
import 'datatables.net-buttons-bs5';
import 'datatables.net-responsive-bs5';

import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css';
import 'datatables.net-searchpanes-bs5/css/searchPanes.bootstrap5.min.css';
import 'datatables.net-searchbuilder-bs5/css/searchBuilder.bootstrap5.min.css';
import 'datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css';
import 'datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css';
import 'datatables.net-columncontrol-bs5/css/columnControl.bootstrap5.min.css';

import './datatables-tabler.css';

export const dtPlugins = {
  columnControl: false,
};

try {
  console.warn('importing columncontrol');
  await import('datatables.net-columncontrol-bs5');
  dtPlugins.columnControl = true;
} catch (error) {
  console.error('[api-grid] ColumnControl plugin unavailable; falling back without it.', error);
}
