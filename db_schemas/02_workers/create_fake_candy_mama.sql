CREATE TABLE workers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    role VARCHAR(100),
    first_appearance_frame INT,
    notes TEXT
);

INSERT INTO workers (name, role, first_appearance_frame, notes)
VALUES (
    'Candy Mama',
    'welder and morale support',
    1081,
    'Carries peppermint torch. First appeared in welding scene at base of Triple Splitter.'
);
