const DEFAULT_OPTS = {
  timeoutMs: 30000,
  pollIntervalMs: 700,
  maxPollMs: 180000,
  retry: { max: 3, baseDelayMs: 400 },
};

function sleep(ms) {
  return new Promise((res) => setTimeout(res, ms));
}

async function fetchWithTimeout(input, init) {
  init = init || {};
  const timeoutMs = init.timeoutMs != null ? init.timeoutMs : 30000;
  const controller = new AbortController();
  const id = setTimeout(() => controller.abort(), timeoutMs);
  try {
    const resp = await fetch(input, { ...init, signal: controller.signal });
    return resp;
  } finally {
    clearTimeout(id);
  }
}

async function pollStatus(method, printerName, requestId, responseId, opts) {
  const o = { ...DEFAULT_OPTS, ...(opts || {}) };
  const serverURL = getServerURL().url;
  const requestURL = serverURL + printerName + "/checkStatus";


  const inquiryData = makeResultInquiryData(
    requestId,
    responseId,
    Math.floor(o.pollIntervalMs / 1000) || 30
  );

  const start = Date.now();
  while (true) {
    if (Date.now() - start > o.maxPollMs) {
      throw new Error("Polling timeout after " + o.maxPollMs + "ms");
    }

    const res = await fetchWithTimeout(requestURL, {
      method,
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: inquiryData,
      timeoutMs: o.timeoutMs,
    });

    if (res.status === 404) throw new Error("No printers");
    if (!res.ok) throw new Error("Cannot connect to server (status " + res.status + ")");

    const json = await res.json(); // { RequestID, ResponseID, Result }
    const ret = json.Result || "";

    if (ret.includes("ready") || ret.includes("progress")) {
      await sleep(o.pollIntervalMs);
      continue;
    }
    return json;
  }
}


async function requestPrintAsync(printerName, submitPayload, opts) {
  const o = { ...DEFAULT_OPTS, ...(opts || {}) };
  const serverURL = getServerURL().url;
  const requestURL = serverURL + printerName;

  let attempt = 0;
  while (true) {
    try {
      const res = await fetchWithTimeout(requestURL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: submitPayload,
        timeoutMs: o.timeoutMs,
      });

      if (res.status === 404) throw new Error('No printers');
      if (!res.ok) throw new Error('Cannot connect to server (status ' + res.status + ')');

      const json = await res.json();
      const ret = json.Result || '';

      if (ret.includes('ready') || ret.includes('progress')) {
        return await pollStatus('POST', printerName, json.RequestID, json.ResponseID, o);
      }
      return json; // success / duplicated / error...
    } catch (e) {
      const msg = String(e && e.message ? e.message : e);
      const retriable =
        (e && e.name === 'AbortError') ||
        /Cannot connect to server/.test(msg) ||
        /^5\d\d$/.test((msg.match(/\b(\d{3})\b/) || [])[1] || '');

      if (retriable && attempt < o.retry.max) {
        attempt++;
        const delay = o.retry.baseDelayMs * Math.pow(2, attempt - 1);
        await sleep(delay);
        continue;
      }
      throw e;
    }
  }
}


function AsyncQueue(concurrency) {
  this.concurrency = concurrency || 1;
  this.q = [];
  this.running = 0;
}
AsyncQueue.prototype.enqueue = function (task) {
  return new Promise((resolve, reject) => {
    const job = async () => {
      try {
        this.running++;
        const result = await task();
        resolve(result);
      } catch (e) {
        reject(e);
      } finally {
        this.running--;
        this._runNext();
      }
    };
    this.q.push(job);
    this._runNext();
  });
};
AsyncQueue.prototype._runNext = function () {
  while (this.running < this.concurrency && this.q.length > 0) {
    const job = this.q.shift();
    if (job) job();
  }
};

const printerQueues = new Map();
function getPrinterQueue(printerName, concurrency) {
  const c = concurrency || 1;
  if (!printerQueues.has(printerName)) {
    printerQueues.set(printerName, new AsyncQueue(c));
  }
  return printerQueues.get(printerName);
}

async function printOne(printerName, strSubmit) {
  return requestPrintAsync(printerName, strSubmit, {
    timeoutMs: 20000,
    pollIntervalMs: 800,
    maxPollMs: 180000,
    retry: { max: 2, baseDelayMs: 500 },
  });
}

async function printManySequential(printerName, submits) {
  const queue = getPrinterQueue(printerName, 1);
  return Promise.all(
    submits.map((payload) => queue.enqueue(() => requestPrintAsync(printerName, payload)))
  );
}

async function printManyLimitedParallel(printerName, submits, concurrency) {
  const c = concurrency || 2;
  const queue = getPrinterQueue(printerName, c);
  return Promise.all(
    submits.map((payload) => queue.enqueue(() => requestPrintAsync(printerName, payload)))
  );
}

function requestPrintAsyncWithCallback(printerName, strSubmit, cb) {
  requestPrintAsync(printerName, strSubmit)
    .then((r) => cb(((r && r.ResponseID) || '') + ':' + (r && r.Result)))
    .catch((e) => cb(String((e && e.message) || e)));
}