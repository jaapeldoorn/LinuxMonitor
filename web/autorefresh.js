document.addEventListener('DOMContentLoaded', function () {
  const checkbox = document.getElementById('autorefresh');
  const refreshInterval = parseInt(checkbox.dataset.interval, 10) * 1000;

  let refreshTimer;

  function toggleRefresh() {
    if (checkbox.checked) {
      refreshTimer = setInterval(() => {
        location.reload();
      }, refreshInterval);
    } else {
      clearInterval(refreshTimer);
    }
  }

  if (checkbox) {
    toggleRefresh();
    checkbox.addEventListener('change', toggleRefresh);
  }
});

