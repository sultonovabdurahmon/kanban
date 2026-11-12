# Модели мантиқии додаҳо: kanban

## 1. Ҷадвали ибтидоӣ (Excel-и даста)

Дастаҳо ҳоло вазифаҳоро дар як ҷадвали Excel пеш мебаранд (додаҳо тахайюлӣ):

| Вазифа | Лоиҳа | Роҳбари лоиҳа | Иштирокчиёни лоиҳа | Иҷрокунанда | Мӯҳлат | Ҳолат | Шарҳҳо |
|---|---|---|---|---|---|---|---|
| Тарҳи саҳифаи вуруд | Сомонаи мағоза | Каримов Ш. | Раҳимов Ф., Назарова М. | Раҳимов Ф. | 20.11.2026 | Дар иҷро | Раҳимов: «ним тайёр»; Каримов: «ранг иваз шавад» |
| Пойгоҳи додаҳо | Сомонаи мағоза | Каримов Ш. | Раҳимов Ф., Назарова М. | Назарова М. | 15.11.2026 | Барои иҷро | — |
| Санҷиши барнома | Барномаи мобилӣ | Саидов А. | Саидов А., Раҳимов Ф. | Раҳимов Ф. | 01.12.2026 | Иҷрошуда | Саидов: «қабул шуд» |

Мушкилот: ном ва ҳолати як лоиҳа дар ҳар сатр такрор мешавад, дар як ҳуҷайра якчанд қимат ҳаст, таърихи тағйирот нест.

## 2. Нормализатсия

### 1ШН

| Чӣ 1ШН-ро вайрон мекунад | Чӣ тавр ислоҳ шуд |
|---|---|
| «Иштирокчиёни лоиҳа» — якчанд қимат дар як ҳуҷайра | ҷадвали алоҳидаи `project_members`: як сатр = як иштирокчии як лоиҳа |
| «Шарҳҳо» — якчанд шарҳ дар як ҳуҷайра | ҷадвали `task_comments`: як сатр = як шарҳ |
| «Мӯҳлат» ҳамчун матн | майдони `due_date` навъи `DATE` |

### 2ШН

Калиди ибтидоӣ — таркиби табиӣ (Вазифа, Лоиҳа). Вобастагиҳои қисмӣ:
- «Роҳбари лоиҳа» танҳо аз «Лоиҳа» вобаста аст, на аз тамоми калид → ҷадвали `projects` (`owner_id`).
- Рӯйхати иштирокчиён низ танҳо аз «Лоиҳа» вобаста аст → `project_members`.

Пас аз ҷудо кардан ҳар ҷадвал калиди суррогатии оддии `id` дорад, бинобар ин вобастагиҳои қисмӣ намонданд.

### 3ШН

| Вобастагии транзитивӣ | Ҳал |
|---|---|
| вазифа → ҳолат (сутун) → тартиб ва ном | ҷадвали `columns`; дар `tasks` танҳо `column_id` |
| вазифа → иҷрокунанда → ном ва email | ҷадвали `users`; дар `tasks` танҳо `assignee_id` |
| вазифа → лоиҳа → роҳбар → ном ва email | `projects.owner_id` → `users` |
| вазифа → таърих (кӣ, кай, чӣ) | ҷадвали `task_history`, на майдони матнӣ дар вазифа |

Санҷиш: тағйири номи сутун як сатрро дар `columns` иваз мекунад, на сатрҳои ҳамаи вазифаҳои он.

**Истисно аз 3ШН (қасдан).** Дар `tasks` ҳам `column_id`, ҳам `project_id` ҳаст, дар ҳоле ки `project_id`-ро аз `columns.project_id` ёфтан мумкин аст. Сабаб: санҷиши ҳуқуқ ва филтрҳо бе `JOIN` кор мекунанд. Мувофиқатро сервер нигоҳ медорад: ҳангоми сохтан ва кашидани корт `column_id` бо `project_id` санҷида мешавад (`SELECT 1 FROM columns WHERE id = ? AND project_id = ?`).

## 3. Моҳиятҳо

### users — корбарон
| Атрибут | Навъ | Калид | NULL | Тавсиф |
|---|---|---|---|---|
| id | INT | PK | не | |
| name | VARCHAR(100) | | не | ном ва насаб |
| email | VARCHAR(150) | UNIQUE | не | воридшавӣ |
| password_hash | VARCHAR(255) | | не | ҳеши паролӣ (`password_hash`) |
| created_at | TIMESTAMP | | не | санаи сабти ном |

### projects — лоиҳаҳо
| Атрибут | Навъ | Калид | NULL | Тавсиф |
|---|---|---|---|---|
| id | INT | PK | не | |
| name | VARCHAR(150) | | не | ном |
| description | TEXT | | ҳа | тавсиф |
| owner_id | INT | FK → users.id | не | созандаи лоиҳа |
| created_at | TIMESTAMP | | не | санаи сохтан |

### project_members — иштирокчиёни лоиҳа (ҷадвали пайвасткунанда)
| Атрибут | Навъ | Калид | NULL | Тавсиф |
|---|---|---|---|---|
| id | INT | PK | не | |
| project_id | INT | FK → projects.id | не | лоиҳа |
| user_id | INT | FK → users.id | не | корбар |
| role | ENUM('manager','member') | | не | нақш дар лоиҳа, пешфарз `member` |

`UNIQUE (project_id, user_id)`: як корбар дар як лоиҳа як маротиба.

### columns — сутунҳои тахта
| Атрибут | Навъ | Калид | NULL | Тавсиф |
|---|---|---|---|---|
| id | INT | PK | не | |
| project_id | INT | FK → projects.id | не | лоиҳа |
| title | VARCHAR(100) | | не | ном |
| position | INT | | не | тартиб, ≥ 0 |

### tasks — вазифаҳо
| Атрибут | Навъ | Калид | NULL | Тавсиф |
|---|---|---|---|---|
| id | INT | PK | не | |
| project_id | INT | FK → projects.id | не | лоиҳа (ниг. истисно дар 3ШН) |
| column_id | INT | FK → columns.id | не | сутуни ҷорӣ |
| title | VARCHAR(200) | | не | ном |
| description | TEXT | | ҳа | тавсиф |
| assignee_id | INT | FK → users.id | ҳа | иҷрокунанда (метавонад таъин нашуда бошад) |
| due_date | DATE | | ҳа | мӯҳлат |
| priority | ENUM('low','medium','high') | | не | афзалият, пешфарз `medium` |
| position | INT | | не | тартиб дар сутун, ≥ 0 |
| created_by | INT | FK → users.id | не | кӣ сохтааст |
| created_at | TIMESTAMP | | не | санаи сохтан |

### task_comments — шарҳҳо
| Атрибут | Навъ | Калид | NULL | Тавсиф |
|---|---|---|---|---|
| id | INT | PK | не | |
| task_id | INT | FK → tasks.id | не | вазифа |
| user_id | INT | FK → users.id | не | муаллиф |
| body | TEXT | | не | матн |
| created_at | TIMESTAMP | | не | вақти навиштан |

### task_history — таърихи тағйирот
| Атрибут | Навъ | Калид | NULL | Тавсиф |
|---|---|---|---|---|
| id | INT | PK | не | |
| task_id | INT | FK → tasks.id | не | вазифа |
| user_id | INT | FK → users.id | не | кӣ тағйир додааст |
| action | VARCHAR(30) | | не | намуди амал: created, update, move, comment, file |
| details | TEXT | | ҳа | чӣ тағйир ёфт (қимати кӯҳна → нав) |
| created_at | TIMESTAMP | | не | кай |

### task_attachments — замимаҳо
| Атрибут | Навъ | Калид | NULL | Тавсиф |
|---|---|---|---|---|
| id | INT | PK | не | |
| task_id | INT | FK → tasks.id | не | вазифа |
| user_id | INT | FK → users.id | не | кӣ бор кардааст |
| original_name | VARCHAR(255) | | не | номи аслии файл |
| stored_name | VARCHAR(255) | UNIQUE | не | номи тасодуфӣ дар `uploads/` |
| created_at | TIMESTAMP | | не | вақти боркунӣ |

## 4. Алоқаҳо

| Алоқа | Навъ | Калиди берунӣ |
|---|---|---|
| корбар — лоиҳаҳо (созанда) | 1:N | projects.owner_id |
| **корбарон — лоиҳаҳо (иштирок)** | **M:N** | **тавассути project_members** |
| лоиҳа — иштирокчиён | 1:N | project_members.project_id |
| корбар — аъзогиҳо | 1:N | project_members.user_id |
| лоиҳа — сутунҳо | 1:N | columns.project_id |
| лоиҳа — вазифаҳо | 1:N | tasks.project_id |
| сутун — вазифаҳо | 1:N | tasks.column_id |
| корбар — вазифаҳо (иҷрокунанда) | 1:N, ғайриҳатмӣ | tasks.assignee_id |
| корбар — вазифаҳо (созанда) | 1:N | tasks.created_by |
| вазифа — шарҳҳо | 1:N | task_comments.task_id |
| вазифа — таърих | 1:N | task_history.task_id |
| вазифа — замимаҳо | 1:N | task_attachments.task_id |
| корбар — шарҳҳо, таърих, замимаҳо | 1:N (се маротиба) | user_id дар се ҷадвал |

Алоқаи M:N «корбарон — лоиҳаҳо» ба ду алоқаи 1:N тавассути `project_members` ҷудо шудааст: як корбар дар якчанд лоиҳа иштирок мекунад, дар як лоиҳа якчанд корбар ҳаст.

## 5. Маҳдудиятҳои бутунӣ

**UNIQUE**
- `users.email`
- `project_members (project_id, user_id)`
- `task_attachments.stored_name`

**CHECK**
- `columns.position >= 0`
- `tasks.position >= 0`

**Қоидаҳои ON DELETE**

| Калиди берунӣ | Қоида | Сабаб |
|---|---|---|
| projects.owner_id → users | RESTRICT | корбареро, ки лоиҳа дорад, нест кардан мумкин нест |
| project_members.project_id → projects | CASCADE | бо лоиҳа иштирокчиёни он низ нест мешаванд |
| project_members.user_id → users | CASCADE | бо корбар аъзогиҳои ӯ нест мешаванд |
| columns.project_id → projects | CASCADE | сутунҳо бе лоиҳа маъно надоранд |
| tasks.project_id → projects | CASCADE | вазифаҳо бо лоиҳа нест мешаванд |
| tasks.column_id → columns | CASCADE | вазифаҳо бо сутун нест мешаванд |
| tasks.assignee_id → users | SET NULL | вазифа мемонад, иҷрокунанда холӣ мешавад |
| tasks.created_by → users | RESTRICT | муаллифи вазифаро нест кардан мумкин нест |
| task_comments.task_id, task_history.task_id, task_attachments.task_id → tasks | CASCADE | бо вазифа шарҳ, таърих ва замимаҳо нест мешаванд |
| task_comments.user_id, task_history.user_id, task_attachments.user_id → users | RESTRICT | таърих ва шарҳҳо муаллиф доранд |

## 6. Тағйирот нисбат ба схемаи кори лаб. 7

Дар `schema.sql` се банд илова шуд: калиди берунии `tasks.created_by`, `UNIQUE` барои `stored_name`, ду `CHECK` барои `position`. Барои пойгоҳи аллакай сохташуда:

```sql
ALTER TABLE tasks ADD CONSTRAINT fk_tasks_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT;
ALTER TABLE task_attachments ADD UNIQUE (stored_name);
ALTER TABLE columns ADD CONSTRAINT chk_columns_position CHECK (position >= 0);
ALTER TABLE tasks ADD CONSTRAINT chk_tasks_position CHECK (position >= 0);
```
