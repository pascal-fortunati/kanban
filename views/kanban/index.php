<?php
declare(strict_types=1);
?>
<div class="max-w-full mx-auto">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 overflow-x-hidden">
        <div class="bg-gray-800 rounded p-3 kanban-column" data-status="todo" ondragover="event.preventDefault()" ondrop="handleDrop(event)">
            <h2 class="text-blue-400 mb-3 inline-flex items-center gap-2"><i class="fa-solid fa-list-check"></i><span>À faire</span></h2>
            <div id="col-todo" class="space-y-2 min-h-[200px]">
                <?php foreach ($todo as $t): ?>
                    <?php $lsraw = (string)($t['labels'] ?? '[]'); $lsb64 = base64_encode($lsraw !== '' ? $lsraw : '[]'); ?>
                    <div class="bg-gray-800 border border-gray-700 hover:border-blue-500 rounded p-3 space-y-2 cursor-pointer transition-colors shadow-sm hover:shadow-md kanban-card" draggable="true" ondragstart="handleDragStart(event)" onclick="openTaskModal(this)" data-id="<?= (int)$t['id'] ?>" data-title="<?= htmlspecialchars($t['title']) ?>" data-priority="<?= htmlspecialchars(strtolower($t['priority'] ?? 'medium')) ?>" data-description="<?= htmlspecialchars($t['description']) ?>" data-labels-b64="<?= htmlspecialchars($lsb64) ?>">
                        <div class="flex items-center justify-between">
                            <div class="font-semibold"><?= htmlspecialchars($t['title']) ?></div>
                            <?php $pr = strtolower($t['priority'] ?? 'medium'); $pt = $pr==='high'?'Élevée':($pr==='low'?'Basse':'Moyenne'); ?>
                            <span class="text-xs px-2 py-1 rounded <?= $pr==='high'?'bg-red-600':($pr==='low'?'bg-green-600':'bg-yellow-600') ?> text-white"><?= htmlspecialchars($pt) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="bg-gray-800 rounded p-3 kanban-column" data-status="in_progress" ondragover="event.preventDefault()" ondrop="handleDrop(event)">
            <h2 class="text-blue-400 mb-3 inline-flex items-center gap-2"><i class="fa-solid fa-spinner"></i><span>En cours</span></h2>
            <div id="col-in_progress" class="space-y-2 min-h-[200px]">
                <?php foreach ($inProgress as $t): ?>
                    <?php $lsraw = (string)($t['labels'] ?? '[]'); $lsb64 = base64_encode($lsraw !== '' ? $lsraw : '[]'); ?>
                    <div class="bg-gray-800 border border-gray-700 hover:border-blue-500 rounded p-3 space-y-2 cursor-pointer transition-colors shadow-sm hover:shadow-md kanban-card" draggable="true" ondragstart="handleDragStart(event)" onclick="openTaskModal(this)" data-id="<?= (int)$t['id'] ?>" data-title="<?= htmlspecialchars($t['title']) ?>" data-priority="<?= htmlspecialchars(strtolower($t['priority'] ?? 'medium')) ?>" data-description="<?= htmlspecialchars($t['description']) ?>" data-labels-b64="<?= htmlspecialchars($lsb64) ?>">
                        <div class="flex items-center justify-between">
                            <div class="font-semibold"><?= htmlspecialchars($t['title']) ?></div>
                            <?php $pr = strtolower($t['priority'] ?? 'medium'); $pt = $pr==='high'?'Élevée':($pr==='low'?'Basse':'Moyenne'); ?>
                            <span class="text-xs px-2 py-1 rounded <?= $pr==='high'?'bg-red-600':($pr==='low'?'bg-green-600':'bg-yellow-600') ?> text-white"><?= htmlspecialchars($pt) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="bg-gray-800 rounded p-3 kanban-column" data-status="review" ondragover="event.preventDefault()" ondrop="handleDrop(event)">
            <h2 class="text-blue-400 mb-3 inline-flex items-center gap-2"><i class="fa-solid fa-magnifying-glass"></i><span>Revue</span></h2>
            <div id="col-review" class="space-y-2 min-h-[200px]">
                <?php foreach ($review as $t): ?>
                    <?php $lsraw = (string)($t['labels'] ?? '[]'); $lsb64 = base64_encode($lsraw !== '' ? $lsraw : '[]'); ?>
                    <div class="bg-gray-800 border border-gray-700 hover:border-blue-500 rounded p-3 space-y-2 cursor-pointer transition-colors shadow-sm hover:shadow-md kanban-card" draggable="true" ondragstart="handleDragStart(event)" onclick="openTaskModal(this)" data-id="<?= (int)$t['id'] ?>" data-title="<?= htmlspecialchars($t['title']) ?>" data-priority="<?= htmlspecialchars(strtolower($t['priority'] ?? 'medium')) ?>" data-description="<?= htmlspecialchars($t['description']) ?>" data-labels-b64="<?= htmlspecialchars($lsb64) ?>">
                        <div class="flex items-center justify-between">
                            <div class="font-semibold"><?= htmlspecialchars($t['title']) ?></div>
                            <?php $pr = strtolower($t['priority'] ?? 'medium'); $pt = $pr==='high'?'Élevée':($pr==='low'?'Basse':'Moyenne'); ?>
                            <span class="text-xs px-2 py-1 rounded <?= $pr==='high'?'bg-red-600':($pr==='low'?'bg-green-600':'bg-yellow-600') ?> text-white"><?= htmlspecialchars($pt) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="bg-gray-800 rounded p-3 kanban-column" data-status="done" ondragover="event.preventDefault()" ondrop="handleDrop(event)">
            <h2 class="text-blue-400 mb-3 inline-flex items-center gap-2"><i class="fa-solid fa-circle-check"></i><span>Terminé</span></h2>
            <div id="col-done" class="space-y-2 min-h-[200px]">
                <?php foreach ($done as $t): ?>
                    <?php $lsraw = (string)($t['labels'] ?? '[]'); $lsb64 = base64_encode($lsraw !== '' ? $lsraw : '[]'); ?>
                    <div class="bg-gray-800 border border-gray-700 hover:border-blue-500 rounded p-3 space-y-2 cursor-pointer transition-colors shadow-sm hover:shadow-md kanban-card" draggable="true" ondragstart="handleDragStart(event)" onclick="openTaskModal(this)" data-id="<?= (int)$t['id'] ?>" data-title="<?= htmlspecialchars($t['title']) ?>" data-priority="<?= htmlspecialchars(strtolower($t['priority'] ?? 'medium')) ?>" data-description="<?= htmlspecialchars($t['description']) ?>" data-labels-b64="<?= htmlspecialchars($lsb64) ?>">
                        <div class="flex items-center justify-between">
                            <div class="font-semibold"><?= htmlspecialchars($t['title']) ?></div>
                            <?php $pr = strtolower($t['priority'] ?? 'medium'); $pt = $pr==='high'?'Élevée':($pr==='low'?'Basse':'Moyenne'); ?>
                            <span class="text-xs px-2 py-1 rounded <?= $pr==='high'?'bg-red-600':($pr==='low'?'bg-green-600':'bg-yellow-600') ?> text-white"><?= htmlspecialchars($pt) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    </div>
    <div id="view-modal" class="fixed inset-0 hidden items-center justify-center modal">
        <div class="absolute inset-0 bg-gray-900 bg-opacity-60 modal-overlay"></div>
        <div class="relative z-10 bg-gray-800 rounded-lg shadow-xl ring-1 ring-gray-700 w-full max-w-3xl mx-4 modal-content">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700">
                <div class="flex items-center gap-2">
                    <div id="view-title" class="text-lg font-semibold text-gray-100"></div>
                    <span id="view-priority" class="text-xs px-2 py-1 rounded bg-yellow-600 text-white"></span>
                </div>
                <div class="inline-flex items-center gap-2">
                    <button class="px-3 py-1 rounded bg-blue-600 hover:bg-blue-500 text-white text-xs" onclick="editCurrentTask()"><i class="fa-solid fa-pen mr-1"></i>Éditer</button>
                    <button class="px-3 py-1 rounded bg-red-600 hover:bg-red-500 text-white text-xs" onclick="deleteCurrentTask()"><i class="fa-solid fa-trash mr-1"></i>Supprimer</button>
                    <button class="inline-flex items-center justify-center w-8 h-8 rounded hover:bg-gray-700 text-gray-300" onclick="closeTaskModal()"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
            <div class="px-4 py-4 space-y-4">
                <div id="view-labels" class="flex flex-wrap gap-2"></div>
                <div class="text-sm text-gray-300"><pre><code id="view-code" class="language-markdown"></code></pre></div>
            </div>
        </div>
    </div>
</div>