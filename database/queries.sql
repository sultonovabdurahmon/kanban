-- database/queries.sql — дархостҳои назоратӣ барои kanban
USE kanban;
SET NAMES utf8mb4;
-- Санаи ҳисоб собит аст, то натиҷаҳо такроршаванда бошанд (барои кори воқеӣ иваз кунед: CURDATE())
SET @today = '2026-11-12';

-- 1. ФТ-05, ФТ-06. Тахтаи лоиҳаи 1: кортҳо бо сутун ва иҷрокунанда (JOIN-и се ҷадвал)
SELECT c.title AS `сутун`, t.title AS `вазифа`, u.name AS `иҷрокунанда`, t.priority AS `афзалият`, t.due_date AS `мӯҳлат`
FROM tasks t
JOIN columns c ON c.id = t.column_id
LEFT JOIN users u ON u.id = t.assignee_id
WHERE t.project_id = 1
ORDER BY c.position, t.position;

-- 2. ФТ-02. Ҷустуҷӯи корбар аз рӯи ибтидои ном барои иловаи иштирокчӣ (LIKE бо ҳарфҳои кириллӣ)
SELECT id, name, email FROM users WHERE name LIKE 'Ғ%';

-- 3. ФТ-04. Шумораи вазифаҳо аз рӯи лоиҳа ва афзалият (GROUP BY + COUNT)
SELECT p.name AS `лоиҳа`, t.priority AS `афзалият`, COUNT(*) AS `шумора`
FROM tasks t
JOIN projects p ON p.id = t.project_id
GROUP BY p.id, p.name, t.priority
ORDER BY p.id, FIELD(t.priority, 'high', 'medium', 'low');

-- 4. ФТ-05. Иштирокчиёни лоиҳаи 1 бо шумораи вазифаҳояшон; касе бе вазифа низ намоён аст (LEFT JOIN)
SELECT u.name AS `иштирокчӣ`, pm.role AS `нақш`, COUNT(t.id) AS `вазифаҳо`
FROM project_members pm
JOIN users u ON u.id = pm.user_id
LEFT JOIN tasks t ON t.assignee_id = u.id AND t.project_id = pm.project_id
WHERE pm.project_id = 1
GROUP BY u.id, u.name, pm.role
ORDER BY `вазифаҳо` DESC, u.name;

-- 5. ФТ-11. Вазифаҳои мӯҳлаташон гузашта: дар сутуни охирин (иҷрошуда) нест (филтр аз рӯи ҳолат)
SELECT t.id, t.title AS `вазифа`, u.name AS `иҷрокунанда`, t.due_date AS `мӯҳлат`, c.title AS `сутун`
FROM tasks t
JOIN columns c ON c.id = t.column_id
LEFT JOIN users u ON u.id = t.assignee_id
WHERE t.due_date < @today
  AND t.column_id <> (SELECT c2.id FROM columns c2
                      WHERE c2.project_id = t.project_id
                      ORDER BY c2.position DESC, c2.id DESC LIMIT 1)
ORDER BY t.due_date;

-- 6. ФТ-07. Таърихи вазифаи 1: саҳифаи аввал (3 сабт) ва дуюм (LIMIT/OFFSET)
SELECT h.created_at AS `кай`, u.name AS `кӣ`, h.action AS `амал`, h.details AS `чӣ`
FROM task_history h
JOIN users u ON u.id = h.user_id
WHERE h.task_id = 1
ORDER BY h.created_at DESC, h.id DESC
LIMIT 3 OFFSET 0;

SELECT h.created_at AS `кай`, u.name AS `кӣ`, h.action AS `амал`, h.details AS `чӣ`
FROM task_history h
JOIN users u ON u.id = h.user_id
WHERE h.task_id = 1
ORDER BY h.created_at DESC, h.id DESC
LIMIT 3 OFFSET 3;

-- 7. Санҷиши индексҳо: дар сутуни key бояд номи индекс бошад.
-- Дар ҷадвали хурд (13 сатр) оптимайзер баъзан скани пурраро интихоб мекунад; бо ҳаҷми калон индекс истифода мешавад.
EXPLAIN SELECT id FROM tasks WHERE due_date < @today;                                  -- key: idx_tasks_due_date
EXPLAIN SELECT * FROM task_history WHERE task_id = 1 ORDER BY created_at DESC;         -- key: idx_history_task_created
