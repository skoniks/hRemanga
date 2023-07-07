<?php

ini_set('display_errors', 1);
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];

try {
  $host = 'localhost';
  $name = 'remanga';
  $user = 'remanga';
  $pass = 'EwDhEj2D2E5b24I8';
  $db = new PDO(
    "mysql:host=$host;dbname=$name",
    $user,
    $pass
  );
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $db->exec('set names utf8mb4');
} catch (PDOException $e) {
  echo json_encode([
    'success' => false,
    'message' => $e->getMessage()
  ]);
  exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
  // header('Location: https://github.com/skoniks/hRemanga');
  if ($id = $_REQUEST['id'] ?? false) {
    $query = "SELECT pages FROM `chapters` WHERE id = $id";
    $data = $db->query($query)->fetch();
    $pages = json_decode($data['pages'], true);
    foreach ($pages as $page) {
      foreach ($page as $image) {
        echo "<div style='text-align:center;'><img src='$image[link]' /></div>" . PHP_EOL;
      }
    }
    exit();
  } else {
    $offset = intval($_REQUEST['offset'] ?? 0);
    $query = "SELECT chapter, MAX(id) as id FROM `chapters` GROUP BY chapter ORDER BY id DESC LIMIT 10 OFFSET $offset";
    $data = $db->query($query)->fetchAll();
    $ids = implode(',', array_column($data, 'id'));
    $query = "SELECT id, chapter, pages FROM `chapters` WHERE id IN($ids) ORDER BY id DESC";
    $data = $db->query($query)->fetchAll();
    $chapters = [];
    foreach ($data as $chapter) {
      $images = [];
      $pages = json_decode($chapter['pages'], true);
      foreach ($pages as $page) {
        foreach ($page as $image) {
          $images[] = $image['link'];
        }
      }
      if (count($images)) {
        preg_match("/images\/(.*?)\//", $images[0], $matches);
        $chapter_data = get_chapter($chapter['chapter']);
        $title_data = get_title($matches[1]);
        $chapters[] = [
          'name' => $title_data['content']['rus_name'],
          'chapter' => $chapter_data['content']['chapter'],
          'dir' => $matches[1],
          'id' => $chapter['chapter'],
          'images' => $images,
          'id2' => $chapter['id']
        ];
      }
    }
    echo '<table>';
    foreach ($chapters as $chapter) {
      echo '<tr>';
      echo "<td><a href='https://remanga.org/manga/$chapter[dir]' target='_blank'>$chapter[name]</a></td>";
      echo "<td><a href='https://remanga.org/manga/$chapter[dir]/ch$chapter[id]' target='_blank'>$chapter[chapter]</a></td>";
      echo "<td>";
      foreach ($chapter['images'] as $key => $value) {
        echo "<a href='$value' target='_blank'>$key</a>" . PHP_EOL;
      }
      echo "<a href='/?id=$chapter[id2]' target='_blank'>Просмотр</a>" . PHP_EOL;
      echo "</td>";
      echo '</tr>';
    }
    echo '</table>';
    $next = $offset + 10;
    echo "<hr><a href='/?offset=$next'>Далее</a>";
    exit();
  }
} else {
  switch ($_REQUEST['action'] ?? false) {
    case 'load':
    case 'download':
      if (!$chapter = $_REQUEST['chapter'] ?? false) {
        echo json_encode([
          'success' => false,
          'message' => 'Глава не указана'
        ]);
        exit();
      }
      $chapter = $db->quote($chapter);
      $query = "SELECT * FROM chapters WHERE chapter = $chapter ORDER BY id DESC";
      if (!$data = $db->query($query)->fetch()) {
        echo json_encode([
          'success' => false,
          'message' => 'Глава не найдена'
        ]);
        exit();
      }
      echo json_encode([
        'success' => true,
        'pages' => $data['pages'] ?? '[[]]'
      ]);
      exit();
    case 'upload':
      if (!$chapter = $_REQUEST['chapter'] ?? false) {
        echo json_encode([
          'success' => false,
          'message' => 'Глава не указана'
        ]);
        exit();
      }
      if ($pages = $_REQUEST['pages'] ?? false) {
        $ip = $db->quote($ip);
        $pages = $db->quote($pages);
        $chapter = $db->quote($chapter);
        $db->exec("INSERT INTO chapters (chapter, pages, user_ip) VALUES ($chapter, $pages, $ip)");
        echo json_encode([
          'success' => true,
          'message' => 'Глава загружена'
        ]);
        exit();
      } else if ($user = $_REQUEST['user'] ?? false) {
        $user = json_decode($user, true);
        if (!isset($user['id'], $user['email'], $user['token'])) {
          echo json_encode([
            'success' => false,
            'message' => 'Ошибка',
          ]);
          exit();
        }

        $url = 'https://api.remanga.org/api/titles/chapters/' . $chapter . '/';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
          'Content-Type: application/json',
          'Authorization: bearer ' . $user['token']
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_URL, $url);
        $data = curl_exec($ch);
        curl_close($ch);

        if (!$data = json_decode($data, true)) {
          echo json_encode([
            'success' => false,
            'message' => 'Ошибка',
          ]);
          exit();
        }
        if ($data['content']['is_paid'] && $data['content']['is_bought']) {
          $pages = json_encode($data['content']['pages'], JSON_UNESCAPED_SLASHES);
          $pages = $db->quote($pages);
          $ip = $db->quote($ip);
          $id = $db->quote($user['id']);
          $email = $db->quote($user['email']);
          $chapter = $db->quote($chapter);
          $db->exec("INSERT INTO chapters (chapter, pages, user_id, user_email, user_ip) VALUES ($chapter, $pages, $id, $email, $ip)");
          echo json_encode([
            'success' => true,
            'message' => 'Глава загружена'
          ]);
          exit();
        } else {
          echo json_encode([
            'success' => true,
          ]);
          exit();
        }
      } else {
        echo json_encode([
          'success' => false,
          'message' => 'Ошибка',
        ]);
        exit();
      }
      exit();
    default:
      echo json_encode([
        'success' => false,
        'message' => 'Ошибка',
      ]);
      exit();
  }
}

function get_chapter($id) {
  $url = 'https://api.remanga.org/api/titles/chapters/' . $id . '/';
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
  ]);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
  curl_setopt($ch, CURLOPT_URL, $url);
  $data = curl_exec($ch);
  curl_close($ch);
  return json_decode($data, true);
}

function get_title($dir) {
  $url = 'https://api.remanga.org/api/titles/' . $dir . '/';
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
  ]);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
  curl_setopt($ch, CURLOPT_URL, $url);
  $data = curl_exec($ch);
  curl_close($ch);
  return json_decode($data, true);
}
