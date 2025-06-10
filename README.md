# 🧵 Marble Track 3 Project Summary (as of June 2025)

## 🎬 Project Overview
- **Marble Track 3** is a long-term stop motion animation project.
- Started in **2017** and paused in 2022, active again in 2025.
- Filmed using **Dragonframe**, livestreamed with **OBS**.
- Characters (mostly pipe cleaner figures) build a physical marble track.
- Thousands of frames are organized into **snippets**, which will be compiled into a full-length movie.

---

## 🏛 Vision for Museum Installation
- The finished physical track will sit in a **glass display case** in the center of a museum room.
- Surrounding monitors will show staggered playback of the full animation:
  - Casual viewers can scan the room and see progress quickly.
  - Focused viewers can follow one screen, then move leftward to rewind 5 minutes at a time.
- Rob will occasionally demonstrate marbles rolling down the physical track.

---

## 🌐 Website & Data Architecture Goals
- Each **part** has one or more associated **snippets** showing its construction by specific **characters**.
- Action logs look like:

```
G Choppy: cut triple splitter: 1080 - 1308
````

- Rob wants to **store the actual date of frame capture** and **installation dates for parts**.

---

## ✅ **Planned Work for `db.marbletrack3.com` (Next Development Goals)**

### 1. **Create Subdomain and Project Base**

* **Subdomain**: `https://db.marbletrack3.com`
* **Purpose**: A database-driven mirror of `www.marbletrack3.com`, focusing on structured data access.
* **Hosting**: Dreamhost shared hosting
* **Stack**: PHP + MySQL (InnoDB engine recommended)

### 2. **Local Project Setup**

* Create a basic loginable site based on Quick, possibly with 2FA built in

** check DB exists with DBExisteroo.php
*** Look for config username
*** Look for DB TABLE users
*** DNE: create admin user password
**
** login page
** login
** 2FA ???
** admin page
** Lemur: scp_to_example.sh

* Move README.md somewhere safe
* Rewrite README.md to minimal biz
* Save that new repo next to new-DH-whatsit repo

* Keep going:
* Restore README

* Create a **local Git repository**
* Initialize directory structure:

e.g.

  ```
  /public_html/
    index.php
    /workers/
    /parts/
    /snippets/
    /admin/
  ```
* Add `.gitignore`, `.htaccess`, and setup URL routing if desired (e.g., route through `index.php`)

---

---

### 4. **Create Migrations**

* Start with the full SQL schema we finalized
* Include:

  * `workers`, `worker_names`
  * `parts`, `part_histories`, `part_history_translations`
  * `frames`, `snippets`, `frames_2_snippets`
  * `actions`, `part_connections`
* Use raw `.sql` or a PHP-based migration tool

## 🗃️ SQL Database Schema Ideas

### 👷 `workers`

```sql
CREATE TABLE workers (
  worker_id INT AUTO_INCREMENT PRIMARY KEY,
  worker_alias VARCHAR(10) NOT NULL UNIQUE
) COMMENT='Each worker is a character in Marble Track 3. Alias is an ASCII-safe unique code.';
```

```sql
INSERT INTO workers (worker_alias) VALUES
  ('square'),
  ('rab'),
  ('lil'),
  ('cm'),
  ('big'),
  ('ds'),
  ('rg'),
  ('gc'),
  ('mmg'),
  ('pink'),
  ('bpj'),
  ('mrg'),
  ('francois'),
  ('sup'),
  ('gar'),
  ('auto');
```


### 🌐 `worker_names`

```sql
CREATE TABLE worker_names (
  worker_name_id INT AUTO_INCREMENT PRIMARY KEY,
  worker_id INT NOT NULL,
  language_code CHAR(2) NOT NULL,
  worker_name VARCHAR(255) NOT NULL,
  worker_description TEXT,
  FOREIGN KEY (worker_id) REFERENCES workers(worker_id)
    ON DELETE CASCADE
);
```

```sql
INSERT INTO worker_names (worker_id, language_code, worker_name, worker_description) VALUES
  ((SELECT worker_id FROM workers WHERE worker_alias = 'auto'), 'US', 'Autosticks', 'Autosticks are magically animated toothpicks who slide their way onto the set where they need to go.'),
  ((SELECT worker_id FROM workers WHERE worker_alias = 'ds'), 'US', 'Doctor Sugar', 'Doctor Sugar is the project manager; he knows everything that''s going on from a technical perspective'),
  ((SELECT worker_id FROM workers WHERE worker_alias = 'rg'), 'US', 'Reversible Guy', 'Reversible Guy''s head looks the same coming.  He can reverse his direction of travel in an instant and likes to do this when collecting things from off the set.  He can walk through tracks as needed.'),
  ((SELECT worker_id FROM workers WHERE worker_alias = 'cm'), 'US', 'Candy Mama', 'Candy Mama is the feminine powerhouse behind lots of the action; she knows what''s going on and what each worker is up to.'),
  ((SELECT worker_id FROM workers WHERE worker_alias = 'gc'), 'US', 'G Choppy', 'G Choppy is an expert wood cutter, carver, and wood bender.  He cannot walk through tracks, but he can fly as needed.'),
  ((SELECT worker_id FROM workers WHERE worker_alias = 'mmg'), 'US', 'Mr McGlue', 'Mr McGlue has one long green arm which delivers glue to parts that need to be connected.  It takes one second to glue each piece in place.  He cannot fly, but he can walk through tracks.'),
  ((SELECT worker_id FROM workers WHERE worker_alias = 'pink'), 'US', 'Pinky', 'Pinky has an eye for design and is a relatively diligent worker.'),
  ((SELECT worker_id FROM workers WHERE worker_alias = 'square'), 'US', 'Squarehead', 'Squarehead often gets confused, often seen clumsily and not knowing what''s going on.'),
  ((SELECT worker_id FROM workers WHERE worker_alias = 'big'), 'US', 'Big Brother', 'Big Brother, sometimes surly, does actually love his little brother.'),
  ((SELECT worker_id FROM workers WHERE worker_alias = 'lil'), 'US', 'Little Brother', 'Little Brother, focused on his own world, usually plays around on the track by spinning or gymnastically flipping around.'),
  ((SELECT worker_id FROM workers WHERE worker_alias = 'sup'), 'US', 'Super Spoony', 'Super Spoony expertly moves marbles on his head, or are the marbles his head?'),
  ((SELECT worker_id FROM workers WHERE worker_alias = 'gar'), 'US', 'Garinoppi', 'Garinoppi is a skeleton who had a cameo appearance flying the stage rotation bearing into place.'),
  ((SELECT worker_id FROM workers WHERE worker_alias = 'bpj'), 'US', 'Backpack Jack', 'Backpack Jack has a backpack he sometimes uses to carry things, but he cannot reach into it to take them out.'),
  ((SELECT worker_id FROM workers WHERE worker_alias = 'rab'), 'US', 'Rabby', 'Rabby had a cameo appearance to bring the lower base into place.  He knocked the camera off its stand when he walked off set.'),
  ((SELECT worker_id FROM workers WHERE worker_alias = 'mrg'), 'US', 'Mr Greene', 'Mr Greene basically knows everything Dr Sugar knows.  He''s a diligent hard worker.');
```

---

### 🧩 `parts`

```sql
CREATE TABLE parts (
  part_id INT AUTO_INCREMENT PRIMARY KEY,
  part_alias VARCHAR(20) NOT NULL UNIQUE,
  part_name VARCHAR(255) NOT NULL,
  part_description TEXT,
  start_frame_id INT,
  installed_frame_id INT,
  FOREIGN KEY (start_frame_id) REFERENCES frames(frame_id)
    ON DELETE SET NULL,
  FOREIGN KEY (installed_frame_id) REFERENCES frames(frame_id)
    ON DELETE SET NULL
) COMMENT='Some parts are multiple pieces, while other parts are individual pieces; not all pieces have names.';
```

---

### 🕰 `part_histories`

```sql
CREATE TABLE part_histories (
  part_history_id INT AUTO_INCREMENT PRIMARY KEY,
  part_id INT NOT NULL,
  event_date DATE,
  FOREIGN KEY (part_id) REFERENCES parts(part_id)
    ON DELETE CASCADE
);
```

### 🌐 `part_history_translations`

```sql
CREATE TABLE part_history_translations (
  part_history_translation_id INT AUTO_INCREMENT PRIMARY KEY,
  part_history_id INT NOT NULL,
  language_code CHAR(2) NOT NULL,
  history_title VARCHAR(255),
  history_description TEXT,
  FOREIGN KEY (part_history_id) REFERENCES part_histories(part_history_id)
    ON DELETE CASCADE
);
```

---

### 🌐 `part_history_photos`

```sql
CREATE TABLE part_history_photos (
  part_history_photo_id INT AUTO_INCREMENT PRIMARY KEY,
  part_history_id INT NOT NULL,
  photo_sort TINYINT NOT NULL,
  history_photo VARCHAR(255),
  FOREIGN KEY (part_history_id) REFERENCES part_histories(part_history_id)
    ON DELETE CASCADE
);
```

---

### 🎞️ `frames`

```sql
CREATE TABLE frames (
  frame_id INT AUTO_INCREMENT PRIMARY KEY,
  frame_number INT NOT NULL,
  frame_timestamp DATETIME NOT NULL,
  scene_number INT,
  take_number INT
) COMMENT='These are individual images that go together to make the movie of workers building Marble Track 3.';
```

---

### 🎬 `snippets`

```sql
CREATE TABLE snippets (
  snippet_id INT AUTO_INCREMENT PRIMARY KEY,
  snippet_name VARCHAR(255),
  part_id INT,
  worker_id INT,
  snippet_notes TEXT,
  FOREIGN KEY (part_id) REFERENCES parts(part_id)
    ON DELETE SET NULL,
  FOREIGN KEY (worker_id) REFERENCES workers(worker_id)
    ON DELETE SET NULL
);
```

---

### 🔁 `snippets_2_frames`

```sql
CREATE TABLE snippets_2_frames (
  snippet_frame_id INT AUTO_INCREMENT PRIMARY KEY,
  snippet_id INT NOT NULL,
  frame_id INT NOT NULL,
  frame_order_within_snippet INT NOT NULL,
  FOREIGN KEY (snippet_id) REFERENCES snippets(snippet_id)
    ON DELETE CASCADE,
  FOREIGN KEY (frame_id) REFERENCES frames(frame_id)
    ON DELETE CASCADE,
  UNIQUE (snippet_id, frame_order_within_snippet)
);
```

---

### 🎭 `actions`

```sql
CREATE TABLE actions (
  action_id INT AUTO_INCREMENT PRIMARY KEY,
  worker_id INT NOT NULL,
  part_id INT NOT NULL,
  action_label VARCHAR(100),
  start_frame_id INT,
  end_frame_id INT,
  action_notes TEXT,
  FOREIGN KEY (worker_id) REFERENCES workers(worker_id)
    ON DELETE CASCADE,
  FOREIGN KEY (part_id) REFERENCES parts(part_id)
    ON DELETE CASCADE,
  FOREIGN KEY (start_frame_id) REFERENCES frames(frame_id)
    ON DELETE SET NULL,
  FOREIGN KEY (end_frame_id) REFERENCES frames(frame_id)
    ON DELETE SET NULL
) COMMENT='These are some of the things our workers did to create the track.';
```

---

### 🔗 `part_connections`

```sql
CREATE TABLE part_connections (
  connection_id INT AUTO_INCREMENT PRIMARY KEY,
  from_part_id INT NOT NULL,
  to_part_id INT NOT NULL,
  marble_sizes SET('small', 'medium', 'large') NOT NULL,
  connection_description TEXT,
  FOREIGN KEY (from_part_id) REFERENCES parts(part_id)
    ON DELETE CASCADE,
  FOREIGN KEY (to_part_id) REFERENCES parts(part_id)
    ON DELETE CASCADE
) COMMENT='from -> to flows with gravity; marble_sizes can be small, medium, large, or any combo';
```

---

### 7. **Fill in Core Tables**

* Populate `workers` and `worker_names` (done for US/English)
* Use generated SQL `INSERT` statements
* Optional: plan for `JA` translations

---

### 8. **Page Routing: Worker Profile**

* Create URLs like:

  ```
  https://db.marbletrack3.com/workers/US/g_choppy
  ```
* Page should show:

  * Worker name, alias
  * Description
  * Linked actions and snippets

---

### 9. **Friendly Redirects**

* Allow alias-based shortcuts:

  ```
  /workers/US/gc  →  /workers/US/g_choppy
  /workers/JA/cm  →  /workers/JA/candy_mama
  ```
* Implement using `.htaccess` or internal PHP mapping

---

### 10. **Admin Dashboard**

* Setup `/admin/` area
* Start with simple PHP forms:

  * Add/edit `workers`, `parts`, `actions`, etc.
* Secure access (e.g., `.htpasswd`, or login session)
* Later: transition to richer form builder or JS-based interface


===============================


---

## 🧠 Engineering: Interactive Switches

Marble size determines interaction with physical mechanisms:

* **El Lifty Lever**: Large marbles lift the lever and move "El Bandarín" flag out of the way so small marbles can pass.
* **Caret Splitter**:

  * Medium marbles must **always go left**.
  * Small marbles may go **left or right**.
  * Rudder motion is **horizontal** on a vertical spindle.

---

## 🛠 Rudder Brainstorm

### Constraints:

* Must be LEFT when medium marbles arrive
* Medium marbles trigger mechanism
* Small marbles do **not** affect it
* Motion is **horizontal**

### Options:

1. **Pre-trigger paddle** only medium marbles touch, nudging rudder left.
2. **One-way flipper** resets left if small marble chooses right.
3. **Weight-based horizontal gear** activated only by medium weight.
4. **Cam wheel** rotated by medium marbles to lock rudder left.

---

Rob is looking for ways to:

* Design reliable **size-based triggers**
* Keep all mechanisms mechanical and passive
* Document and visualize the track logic as a **connected system**

```

### 5. **S3 Frame Storage Planning**

* **Goal**: Store raw animation frames (Dragonframe `X1`) on S3
* **Naming Convention**:

  ```
  s3://marbletrack3-frames/scene_2/take_11/X1/0001.png
  ```
* Each uploaded take should:

  * Include `scene_number`, `take_number`
  * Log metadata like `frame_number`, timestamp, size

---

### 6. **Create Snippets from S3 Frames**

* Build a local tool (CLI or PHP) to:

  * Select frames from S3 based on scene/take
  * Define snippets via GUI or a `.json`/.md sidecar
  * Generate `snippets` entries and link frames via `frames_2_snippets`

