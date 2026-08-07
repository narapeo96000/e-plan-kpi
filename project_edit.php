<?php
require_once __DIR__ . '/db.php';
requireLogin();

$isAdmin = isAdmin();
$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

$projectId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$editing = $projectId > 0;
$project = null;

if ($editing) {
    $res = $conn->query("SELECT * FROM okr_projects WHERE id = $projectId LIMIT 1");
    if ($res && $res->num_rows) {
        $project = $res->fetch_assoc();
        // permission
        if (!$isAdmin && intval($project['owner_user_id']) !== $currentUserId) {
            die('ไม่มีสิทธิ์แก้ไขโครงการนี้');
        }
        // load objectives and KRs
        $objectives = [];
        $oRes = $conn->query("SELECT * FROM okr_objectives WHERE project_id = $projectId ORDER BY id ASC");
        if ($oRes) {
            while ($o = $oRes->fetch_assoc()) {
                $o['krs'] = [];
                $kRes = $conn->query("SELECT * FROM okr_objective_krs WHERE objective_id = " . intval($o['id']) . " ORDER BY id ASC");
                if ($kRes) while ($k = $kRes->fetch_assoc()) $o['krs'][] = $k;
                $objectives[] = $o;
            }
        }
    } else {
        die('ไม่พบข้อมูลโครงการ');
    }
} else {
    $objectives = [];
}

// Non-admin users may only create/edit OKR projects owned by themselves
$lockedOwner = !$isAdmin;
$selectedOwnerId = $lockedOwner ? $currentUserId : ($editing && !empty($project['owner_user_id']) ? (int)$project['owner_user_id'] : '');

// Simple list of users for co-responsible selection
$users = [];
$uRes = $conn->query("SELECT id, username, name FROM users WHERE status = 'active' ORDER BY name ASC");
if ($uRes) while ($u = $uRes->fetch_assoc()) $users[] = $u;

?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo $editing ? 'แก้ไขโครงการ' : 'สร้างโครงการใหม่'; ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800">
  <div class="max-w-4xl mx-auto py-8">
    <div class="bg-white p-6 rounded shadow">
      <h1 class="text-2xl font-semibold mb-4"><?php echo $editing ? 'แก้ไขโครงการ' : 'สร้างโครงการใหม่'; ?></h1>
      <form id="projectForm" action="project_save.php" method="post">
        <input type="hidden" name="id" value="<?php echo $editing ? intval($project['id']) : 0; ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium">ชื่อโครงการ</label>
            <input name="project_name" required class="w-full border rounded px-3 py-2" value="<?php echo $editing ? htmlspecialchars($project['project_name']) : ''; ?>">
          </div>
          <div>
            <label class="block text-sm font-medium">หน่วยงานที่รับผิดชอบ</label>
            <input name="department" class="w-full border rounded px-3 py-2" value="<?php echo $editing ? htmlspecialchars($project['department']) : ''; ?>">
          </div>
          <div>
            <label class="block text-sm font-medium">ผู้รับผิดชอบโครงการ</label>
            <select name="owner_user_id" class="w-full border rounded px-3 py-2" <?php echo $lockedOwner ? 'disabled' : ''; ?>>
              <option value="">-- เลือก --</option>
              <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $selectedOwnerId == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['username']) ?>)</option>
              <?php endforeach; ?>
            </select>
            <?php if ($lockedOwner): ?>
              <input type="hidden" name="owner_user_id" value="<?= (int)$currentUserId ?>">
            <?php endif; ?>
          </div>
          <div>
            <label class="block text-sm font-medium">ผู้รับผิดชอบร่วม (คั่นด้วย ; )</label>
            <input name="co_responsibles" class="w-full border rounded px-3 py-2" value="<?php echo $editing ? htmlspecialchars($project['co_responsibles']) : ''; ?>">
          </div>
          <div>
            <label class="block text-sm font-medium">แหล่งที่มาของงบประมาณ</label>
            <input name="funding_source" class="w-full border rounded px-3 py-2" value="<?php echo $editing ? htmlspecialchars($project['funding_source']) : ''; ?>">
          </div>
          <div>
            <label class="block text-sm font-medium">แสดงงบประมาณภาพรวม?</label>
            <select name="show_overall_budget" class="w-full border rounded px-3 py-2">
              <option value="1" <?= !$editing || $project['show_overall_budget'] ? 'selected' : '' ?>>แสดง</option>
              <option value="0" <?= $editing && !$project['show_overall_budget'] ? 'selected' : '' ?>>ไม่แสดง</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium">สถานะ</label>
            <select name="status" class="w-full border rounded px-3 py-2">
              <option value="ยังไม่เริ่ม" <?= $editing && $project['status'] == 'ยังไม่เริ่ม' ? 'selected' : '' ?>>ยังไม่เริ่ม</option>
              <option value="ระหว่างดำเนินการ" <?= $editing && $project['status'] == 'ระหว่างดำเนินการ' ? 'selected' : '' ?>>ระหว่างดำเนินการ</option>
              <option value="เสร็จสิ้น" <?= $editing && $project['status'] == 'เสร็จสิ้น' ? 'selected' : '' ?>>เสร็จสิ้น</option>
              <option value="ระงับ" <?= $editing && $project['status'] == 'ระงับ' ? 'selected' : '' ?>>ระงับ</option>
            </select>
          </div>
        </div>

        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium">งบประมาณที่ได้รับ (บาท)</label>
            <input type="number" step="0.01" name="budget_received" class="w-full border rounded px-3 py-2" value="<?php echo $editing ? htmlspecialchars($project['budget_received']) : ''; ?>">
          </div>
          <div>
            <label class="block text-sm font-medium">งบที่ใช้ไป (บาท)</label>
            <input type="number" step="0.01" name="budget_spent" class="w-full border rounded px-3 py-2" value="<?php echo $editing ? htmlspecialchars($project['budget_spent']) : ''; ?>">
          </div>
          <div>
            <label class="block text-sm font-medium">ค่าความก้าวหน้า (%)</label>
            <input type="number" step="0.01" name="progress_percent" class="w-full border rounded px-3 py-2" value="<?php echo $editing ? htmlspecialchars($project['progress_percent']) : '0'; ?>">
          </div>
        </div>

        <div class="mt-4">
          <label class="block text-sm font-medium">กิจกรรมที่ดำเนินโครงการ</label>
          <textarea name="activities_summary" rows="3" class="w-full border rounded px-3 py-2"><?php echo $editing ? htmlspecialchars($project['activities_summary']) : ''; ?></textarea>
        </div>

        <div class="mt-4">
          <label class="block text-sm font-medium">สรุปผลการดำเนินโครงการ</label>
          <textarea name="overall_result" rows="3" class="w-full border rounded px-3 py-2"><?php echo $editing ? htmlspecialchars($project['overall_result']) : ''; ?></textarea>
        </div>

        <div class="mt-6">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-medium">วัตถุประสงค์ (Objectives)</h2>
            <button id="addObjBtn" type="button" class="px-3 py-1 bg-green-600 text-white rounded">+ เพิ่ม Objective</button>
          </div>
          <div id="objContainer" class="space-y-4">
            <!-- objectives injected here -->
          </div>
        </div>

        <div class="mt-6 flex justify-between items-center">
          <div class="text-sm text-gray-600">ผู้บันทึกล่าสุด: <?php echo $editing ? htmlspecialchars($project['last_updated_by']) . ' • ' . htmlspecialchars($project['last_updated_at']) : '-'; ?></div>
          <div class="space-x-2">
            <button id="previewBtn" type="button" class="px-4 py-2 border rounded">Preview</button>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">บันทึก</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <template id="objTemplate">
    <div class="p-4 border rounded bg-gray-50">
      <div class="flex justify-between items-center">
        <h3 class="font-semibold">Objective</h3>
        <div class="space-x-2">
          <button type="button" class="removeObjBtn text-red-600">ลบ</button>
        </div>
      </div>
      <div class="mt-3">
        <textarea name="objectives[][objective_text]" required class="w-full border rounded px-3 py-2" placeholder="ระบุ Objective"></textarea>
      </div>
      <div class="mt-3">
        <div class="flex items-center justify-between mb-2">
          <div class="text-sm font-medium">Key Results</div>
          <button type="button" class="addKRInlineBtn px-2 py-1 bg-green-500 text-white rounded">+ KR</button>
        </div>
        <div class="kr-list space-y-3">
          <!-- KR items -->
        </div>
      </div>
    </div>
  </template>

  <template id="krInlineTemplate">
    <div class="p-3 border rounded bg-white">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
        <div class="md:col-span-2">
          <input name="objectives[][krs][][kr_text]" class="w-full border rounded px-2 py-1" placeholder="ตัวชี้วัดและค่าเป้าหมาย">
        </div>
        <div>
          <input name="objectives[][krs][][target_number]" class="w-full border rounded px-2 py-1" placeholder="เป้าหมายตัวเลข">
        </div>
      </div>
      <div class="mt-2 grid grid-cols-1 md:grid-cols-3 gap-2">
        <div>
          <input name="objectives[][krs][][unit]" class="w-full border rounded px-2 py-1" placeholder="หน่วยนับ">
        </div>
        <div class="md:col-span-2">
          <input name="objectives[][krs][][initiative_text]" class="w-full border rounded px-2 py-1" placeholder="กิจกรรมย่อย">
        </div>
      </div>
      <div class="mt-2 flex items-center justify-between">
        <select name="objectives[][krs][][status]" class="border rounded px-2 py-1">
          <option value="ยังไม่เริ่ม">ยังไม่เริ่ม</option>
          <option value="ระหว่างดำเนินการ">ระหว่างดำเนินการ</option>
          <option value="เสร็จสิ้น">เสร็จสิ้น</option>
        </select>
        <button type="button" class="removeKRBtn text-red-600">ลบ KR</button>
      </div>
    </div>
  </template>

  <script>
    (function(){
      const objContainer = document.getElementById('objContainer');
      const objTpl = document.getElementById('objTemplate').content;
      const krTpl = document.getElementById('krInlineTemplate').content;
      const addObjBtn = document.getElementById('addObjBtn');
      const previewBtn = document.getElementById('previewBtn');

      function addObjective(data){
        const node = document.importNode(objTpl, true);
        if (data && data.objective_text) node.querySelector('textarea[name="objectives[][objective_text]"]').value = data.objective_text;
        const krList = node.querySelector('.kr-list');
        if (data && Array.isArray(data.krs)){
          data.krs.forEach(kr => {
            const krNode = document.importNode(krTpl, true);
            const inputs = krNode.querySelectorAll('input, select');
            inputs.forEach(i => {
              const name = i.getAttribute('name');
              if (name.includes('kr_text') && kr.kr_text) i.value = kr.kr_text;
              if (name.includes('target_number') && kr.target_number) i.value = kr.target_number;
              if (name.includes('unit') && kr.unit) i.value = kr.unit;
              if (name.includes('initiative_text') && kr.initiative_text) i.value = kr.initiative_text;
              if (name.includes('status') && kr.status) i.value = kr.status;
            });
            krList.appendChild(krNode);
          });
        } else {
          // add one empty KR
          const krNode = document.importNode(krTpl, true);
          krList.appendChild(krNode);
        }

        // attach events
        node.querySelector('.addKRInlineBtn').addEventListener('click', function(){
          const newKR = document.importNode(krTpl, true);
          node.querySelector('.kr-list').appendChild(newKR);
        });
        node.querySelector('.removeObjBtn').addEventListener('click', function(){ node.remove(); });
        node.querySelector('.kr-list').addEventListener('click', function(e){
          if (e.target && e.target.classList.contains('removeKRBtn')) {
            e.target.closest('.p-3').remove();
          }
        });

        objContainer.appendChild(node);
      }

      addObjBtn.addEventListener('click', function(){ addObjective(); });

      // populate existing objectives if editing (server-side rendered JSON)
      const existing = <?php echo json_encode($objectives, JSON_UNESCAPED_UNICODE); ?>;
      if (existing && existing.length) {
        existing.forEach(o => addObjective(o));
      }

      // preview
      previewBtn.addEventListener('click', function(){
        const form = document.getElementById('projectForm');
        const fd = new FormData(form);
        const obj = {};
        fd.forEach((v,k) => {
          if (!obj[k]) obj[k]=v; else if (Array.isArray(obj[k])) obj[k].push(v); else obj[k]=[obj[k],v];
        });
        const w = window.open('', '_blank', 'width=800,height=600');
        w.document.write('<pre style="white-space:pre-wrap;font-family:Arial,Helvetica,sans-serif;padding:16px">'+JSON.stringify(obj,null,2)+'</pre>');
      });
    })();
  </script>
</body>
</html>
