const express = require('express');
const path = require('path');

const app = express();
const PORT = process.env.PORT || 3000;

app.disable('x-powered-by');
app.use(express.static(__dirname, { extensions: ['html'] }));

app.get('/api/health', (req, res) => {
  res.json({ ok: true });
});

app.get('/api/jobs', async (req, res) => {
  try {
    const appId = process.env.ADZUNA_APP_ID;
    const appKey = process.env.ADZUNA_APP_KEY;

    if (!appId || !appKey) {
      return res.status(500).json({
        error: 'Adzuna API credentials are not configured on the server.'
      });
    }

    const what = String(req.query.q || '').trim();
    const where = String(req.query.location || '').trim();
    const country = String(req.query.country || 'ca').toLowerCase();
    const page = Math.max(1, Number.parseInt(req.query.page, 10) || 1);
    const resultsPerPage = Math.min(50, Math.max(1, Number.parseInt(req.query.results_per_page, 10) || 20));

    const allowedCountries = new Set(['ca', 'us', 'gb', 'au', 'nz']);
    const countryCode = allowedCountries.has(country) ? country : 'ca';

    const apiUrl = new URL(
      `https://api.adzuna.com/v1/api/jobs/${countryCode}/search/${page}`
    );
    apiUrl.searchParams.set('app_id', appId);
    apiUrl.searchParams.set('app_key', appKey);
    apiUrl.searchParams.set('results_per_page', String(resultsPerPage));
    apiUrl.searchParams.set('content-type', 'application/json');
    if (what) apiUrl.searchParams.set('what', what);
    if (where) apiUrl.searchParams.set('where', where);

    const response = await fetch(apiUrl, {
      headers: { 'Accept': 'application/json' }
    });

    if (!response.ok) {
      const body = await response.text();
      console.error('Adzuna error:', response.status, body.slice(0, 500));
      return res.status(502).json({ error: 'Job provider returned an error.' });
    }

    const data = await response.json();
    const results = Array.isArray(data.results) ? data.results.map(job => ({
      id: job.id || null,
      title: job.title || 'Untitled job',
      company: job.company?.display_name || '',
      location: job.location?.display_name || '',
      description: job.description || '',
      created: job.created || null,
      redirect_url: job.redirect_url || '',
      salary_min: job.salary_min ?? null,
      salary_max: job.salary_max ?? null,
      contract_time: job.contract_time || '',
      contract_type: job.contract_type || '',
      category: job.category?.label || ''
    })) : [];

    res.json({
      count: Number(data.count || 0),
      page,
      results
    });
  } catch (err) {
    console.error('Jobs API error:', err);
    res.status(500).json({ error: 'Unable to load jobs right now.' });
  }
});

app.get('*', (req, res) => {
  res.sendFile(path.join(__dirname, 'index.html'));
});

app.listen(PORT, () => {
  console.log(`JobsOther running on port ${PORT}`);
});
