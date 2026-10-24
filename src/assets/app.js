const search = document.querySelector('#table-search');
const rows = [...document.querySelectorAll('#data-rows tr')];
const empty = document.querySelector('#empty-state');
const count = document.querySelector('#result-count');
search?.addEventListener('input', () => {
  const query = search.value.trim().toLocaleLowerCase('vi');
  let visible = 0;
  for (const row of rows) {
    const text = [...row.cells].slice(0, -1).map(cell => cell.textContent).join(' ').toLocaleLowerCase('vi');
    row.hidden = !text.includes(query);
    if (!row.hidden) visible++;
  }
  count.textContent = visible;
  empty.hidden = visible > 0;
  document.querySelector('#empty-title').textContent = rows.length ? 'Không tìm thấy kết quả' : 'Chưa có dữ liệu';
  document.querySelector('#empty-description').textContent = rows.length ? 'Thử tìm bằng mã, tên hoặc thông tin khác.' : 'Bắt đầu bằng cách thêm thông tin ở biểu mẫu bên cạnh.';
});
