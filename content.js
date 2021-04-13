var lastLocation = '';
var lastAppHtml = '';
var checkLocation = () => {
  let html = document.getElementById('app').innerHTML;
  if (location.href != lastLocation) {
    lastLocation = location.href;
    lastAppHtml = html;
    let chapter = getChapter();
    if (chapter) loadChapter(chapter);
  } else if (lastAppHtml != html) {
    lastAppHtml = html;
    let chapter = getChapter();
    if (chapter) loadChapter(chapter);
  }
};
var getChapter = () => {
  let match = location.pathname.match(/manga\/.*\/ch(\d*)/);
  if (match) return match[1];
  return null;
};
var getUser = () => {
  let match = document.cookie.match(/user=(.*?);/);
  if (match) return JSON.parse(decodeURIComponent(match[1]));
  return null;
};
var loadChapter = chapter => {
  let xhr = new XMLHttpRequest();
  xhr.open('GET', 'https://api.remanga.org/api/titles/chapters/' + chapter, true);
  xhr.setRequestHeader('Content-Type', 'application/json');
  let user = getUser();
  if (user) xhr.setRequestHeader('Authorization', 'bearer ' + user.access_token);
  xhr.responseType = 'json';
  xhr.onload = () => {
    if (xhr.response.msg) {
      console.log('Loading ' + chapter + ' from server');
      loadRemote(chapter);
    } else if (xhr.response.content.is_bought) {
      let pages = xhr.response.content.pages;
      console.log('Uploading ' + chapter + ' on server', pages);
      uploadRemote(chapter, pages);
    }
  };
  xhr.send(null);
};
var loadRemote = chapter => {
  chrome.runtime.sendMessage({
    data: {
      action: 'load',
      chapter: chapter
    }
  }, response => {
    if (response.success) {
      let pages = JSON.parse(response.pages);
      let body = document.querySelector('#app>div:first-of-type');
      let html = '';
      for (var row in pages) {
        html += '<div style="margin:auto;display:grid;position:relative;max-width:900px!important;">';
        for (var page in pages[row]) {
          html += '<img src="' + pages[row][page].link + '" style="width: 100%;max-width: 100vw;background-size: 100% 100%;">';
        }
        html += '</div>';
      }
      body.innerHTML = html;
      console.log(body);
    } else {
      console.log(response.message);
    }
  });
};
var uploadRemote = (chapter, pages) => {
  chrome.runtime.sendMessage({
    data: {
      action: 'upload',
      chapter: chapter,
      pages: JSON.stringify(pages)
    }
  }, response => {
    console.log(response.message);
  });
};
setInterval(checkLocation, 200);