HR = {
  // Get user info from cookies
  getUser: () => {
    let match = document.cookie.match(/user=(.*?);/);
    if (match) return JSON.parse(decodeURIComponent(match[1]));
    return null;
  },
  // Check location or content changes
  lastLocation: '',
  lastAppCount: 0,
  checkLocation: () => {
    let chapter = HR.checkChapter();
    if (chapter) {
      let appCount = document.querySelectorAll('#app>div:first-of-type>div').length;
      if (location.href != HR.lastLocation) {
        HR.lastLocation = location.href;
        HR.lastAppCount = appCount;
        HR.downloadLocal(chapter, true);
      } else if (appCount != HR.lastAppCount) {
        HR.lastAppCount = appCount;
        HR.downloadLocal(chapter, false);
      }
    }
  },
  // Get chapter from location
  checkChapter: () => {
    let match = location.pathname.match(/manga\/.*\/ch(\d*)/);
    if (match) return match[1];
    return null;
  },
  // Check is chapter paid
  downloadLocal: (chapter, download = false) => {
    let user = HR.getUser();
    let url = 'https://api.remanga.org/api/titles/chapters/' + chapter + '/';
    let headers = { 'Content-Type': 'application/json' }
    if (user) headers['Authorization'] = 'bearer ' + user.access_token;
    HR.requestLocal(url, 'GET', headers).then(response => {
      if (response.content.is_paid) {
        if (response.content.is_bought && user) {
          HR.uploadRemote(chapter, user);
        } else if (download) {
          HR.downloadRemote(chapter);
        }
      }
    });
  },
  // Upload chapter to remote server
  uploadRemote: (chapter, user) => {
    console.log('HR upload', chapter);
    HR.requestRemote('https://hr.sknx.ru', 'POST', {
      action: 'upload',
      chapter: chapter,
      user: JSON.stringify({
        id: user.id,
        email: user.email,
        token: user.access_token,
      })
    }).then(response => {
      if (!response.success) {
        alert(response.message);
      }
    });
  },
  // Download chapter from remote server
  downloadRemote: (chapter) => {
    console.log('HR download', chapter);
    HR.requestRemote('https://hr.sknx.ru', 'POST', {
      action: 'download',
      chapter: chapter
    }).then(response => {
      if (response.success) {
        HR.drawChapter(response.pages)
      } else {
        alert(response.message);
      }
    });
  },
  // Draw chapter from remote
  drawChapter: (pages) => {
    pages = JSON.parse(pages);
    document.querySelector('#app>div:first-of-type>div').className = ""
    document.querySelector('#app>div:first-of-type>div').innerHTML = pages.map(row =>
      '<div style="margin:auto;display:grid;position:relative;max-width:900px!important;">' +
      row.map(page => '<img src="' + page.link + '" style="width: 100%;max-width: 100vw;background-size: 100% 100%;">').join('') +
      '</div>'
    ).join('');
  },
  // XML Request to local
  requestLocal: (url = '', method = 'GET', headers = {}) => {
    return new Promise(resolve => {
      let xhr = new XMLHttpRequest();
      xhr.open(method, url, true);
      xhr.responseType = 'json';
      for (key in headers) xhr.setRequestHeader(key, headers[key])
      xhr.onload = () => resolve(xhr.response);
      xhr.send(null);
    });
  },
  // Fetch request to remote
  requestRemote: (url = '', method = 'POST', data = {}) => {
    return new Promise(resolve => {
      chrome.runtime.sendMessage({
        url: url, method: method, data: data
      }, response => resolve(response));
    });
  },
  // Check version
  versionCheck: () => {
    let url = 'https://raw.githubusercontent.com/skoniks/hRemanga/master/manifest.json';
    let localManifest = chrome.runtime.getManifest();
    HR.requestRemote(url, 'GET').then(remoteManifest => {
      if (parseFloat(remoteManifest.version) > parseFloat(localManifest.version)) {
        if (confirm('Доступно обновление hRemanga! Перейти на страницу?')) {
          window.open('https://github.com/skoniks/hRemanga');
        }
      }
    });
  }
}
setInterval(HR.checkLocation, 200);
setTimeout(HR.versionCheck, 1000);