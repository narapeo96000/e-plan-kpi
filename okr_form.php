<?php
require_once __DIR__ . '/db.php';
requireLogin();
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>นำเข้า OKR โครงการ</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800">
  <div class="max-w-4xl mx-auto py-10">
    <div class="bg-white p-6 rounded-lg shadow">
      <?php getFlash(); ?>
      <h1 class="text-2xl font-semibold mb-4">นำเข้า OKR โครงการ</h1>
      <form id="okrForm" action="submit_okr.php" method="post">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">ปีงบประมาณ</label>
            <input name="fiscal_year" required class="w-full border rounded px-3 py-2" value="<?php echo htmlspecialchars($fiscal_year); ?>">
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium mb-1">ชื่อโครงการ</label>
            <input name="project_name" required class="w-full border rounded px-3 py-2" placeholder="ชื่อโครงการ...">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">กลุ่มเป้าหมาย (คน)</label>
            <input type="number" name="target_group_count" min="0" class="w-full border rounded px-3 py-2" placeholder="จำนวน">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">งบประมาณ (บาท)</label>
            <input type="number" step="0.01" name="budget" class="w-full border rounded px-3 py-2" placeholder="0.00">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">หน่วยงานที่รับผิดชอบ</label>
            <input name="department" class="w-full border rounded px-3 py-2" placeholder="เช่น กลุ่มนโยบายและแผน">
          </div>
        </div>

        <div class="mt-6">
          <label class="block text-sm font-medium mb-1">Objective (วัตถุประสงค์หลัก)</label>
          <textarea name="objective_text" required rows="4" class="w-full border rounded px-3 py-2" placeholder="ระบุเป้าหมายโดยย่อ..."></textarea>
        </div>

        <div class="mt-6">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-medium">Key Results</h2>
            <button id="addKRBtn" type="button" class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white rounded hover:bg-green-700">+ เพิ่ม Key Result</button>
          </div>

          <div id="krContainer" class="space-y-4">
            <!-- Template inserted here by JS -->
          </div>
        </div>

        <div class="mt-6 flex justify-end">
          <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">บันทึก OKR</button>
        </div>
      </form>
    </div>
  </div>

  <template id="krTemplate">
    <div class="p-4 border rounded bg-gray-50">
      <div class="flex justify-between items-start">
        <h3 class="font-semibold">Key Result</h3>
        <div class="space-x-2">
          <button type="button" class="removeKRBtn text-red-600">ลบ</button>
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3">
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">ตัวชี้วัดและค่าเป้าหมาย</label>
          <input name="key_results[][kr_text]" required class="w-full border rounded px-3 py-2" placeholder="เช่น ร้อยละ 80 ของนักศึกษา...">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">เป้าหมายเชิงปริมาณ</label>
          <input type="number" step="0.01" name="key_results[][target_number]" class="w-full border rounded px-3 py-2" placeholder="ตัวเลข">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">หน่วยนับ</label>
          <input name="key_results[][unit]" class="w-full border rounded px-3 py-2" placeholder="คน / ร้อยละ / ครั้ง">
        </div>
        <div class="md:col-span-3">
          <label class="block text-sm font-medium mb-1">กิจกรรมย่อย (Initiative)</label>
          <input name="key_results[][initiative_text]" class="w-full border rounded px-3 py-2" placeholder="กิจกรรมที่จะทำเพื่อให้บรรลุ KR">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">สถานะ</label>
          <select name="key_results[][status]" class="w-full border rounded px-3 py-2">
            <option value="ยังไม่เริ่ม">ยังไม่เริ่ม</option>
            <option value="ระหว่างดำเนินการ">ระหว่างดำเนินการ</option>
            <option value="เสร็จสิ้น">เสร็จสิ้น</option>
          </select>
        </div>
      </div>
    </div>
  </template>

  <script>
    (function(){
      const krContainer = document.getElementById('krContainer');
      const krTemplate = document.getElementById('krTemplate').content;
      const addBtn = document.getElementById('addKRBtn');

      function addKR(data) {
        const node = document.importNode(krTemplate, true);
        if (data) {
          // populate values if provided
          const inputs = node.querySelectorAll('input, textarea, select');
          inputs.forEach(i => {
            const name = i.getAttribute('name');
            if (!name) return;
            if (name.includes('kr_text') && data.kr_text) i.value = data.kr_text;
            if (name.includes('target_number') && data.target_number) i.value = data.target_number;
            if (name.includes('unit') && data.unit) i.value = data.unit;
            if (name.includes('initiative_text') && data.initiative_text) i.value = data.initiative_text;
            if (name.includes('status') && data.status) i.value = data.status;
          });
        }
        krContainer.appendChild(node);
      }

      // initial blank KR
      addKR();

      addBtn.addEventListener('click', function(){ addKR(); });

      // delegate remove
      krContainer.addEventListener('click', function(e){
        if (e.target && e.target.classList.contains('removeKRBtn')){
          const card = e.target.closest('div[pinned]') || e.target.closest('.p-4');
          if (card) card.remove();
        }
      });
    })();
  </script>
</body>
</html>
