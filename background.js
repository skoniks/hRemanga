chrome.runtime.onMessage.addListener(
  (request, sender, callback) => {
    let data = new FormData();
    for (let i in request.data)
      data.append(i, request.data[i]);
    fetch('https://hr.sknx.ru', {
      method: 'post',
      body: data
    }).then(response => response.json())
      .then(data => callback(data))
    return true;
  }
);