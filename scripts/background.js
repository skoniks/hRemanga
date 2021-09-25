chrome.runtime.onMessage.addListener(
  (request, sender, callback) => {
    let params = { method: request.method }
    if (request.method == 'POST') {
      params.body = new FormData();
      for (let i in request.data)
        params.body.append(i, request.data[i]);
    }
    fetch(request.url, params)
      .then(response => response.json())
      .then(data => callback(data))
    return true;
  }
);