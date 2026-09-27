const health = document.getElementById('health');

fetch('/api/v1/health', { headers: { Accept: 'application/json' } })
  .then(async response => {
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data?.error?.message || 'خطای سرور');
    health.textContent = 'وضعیت سرور: فعال — API آماده است.';
    health.className = 'status ok';
  })
  .catch(() => {
    health.textContent = 'اتصال به API برقرار نشد.';
    health.className = 'status error';
  });
