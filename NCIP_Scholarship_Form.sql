CREATE TABLE USERS (
USER_ID					SERIAL											PRIMARY KEY,
USER_USERNAME			VARCHAR(50)										NOT NULL,
USER_EMAIL				VARCHAR(50)		UNIQUE							NOT NULL,
USER_PWD				VARCHAR(100)									NOT NULL,
USER_ROLE           	VARCHAR(20)     DEFAULT 'USER' NOT NULL, 
USER_PROFILE_PICTURE	VARCHAR(255) 	DEFAULT 'default.png',
USER_TIMEZONE 			VARCHAR(50) 	DEFAULT 'UTC' 					NOT NULL,
USER_CREATED			TIMESTAMPTZ		DEFAULT CURRENT_TIMESTAMP		NOT NULL,
USER_RESET_TOKEN    	VARCHAR(255),
USER_TOKEN_EXPIRY   	TIMESTAMPTZ,
USER_DELETED_AT 		TIMESTAMPTZ,
USER_DELETED_BY 		INTEGER 										REFERENCES USERS(USER_ID)
);

CREATE TABLE ADMINISTRATOR (
	ADMIN_CODE					SERIAL																						PRIMARY KEY,
	ADMIN_APP_NUMBER			VARCHAR(50)																					NOT NULL,
	ADMIN_DOF					DATE																						NOT NULL,
	ADMIN_ACTION_TAKEN_CSC_PO	VARCHAR(150),
	ADMIN_ACTION_TAKEN_RO		VARCHAR(150),
	ADMIN_CADT					VARCHAR(150),
	ADMIN_CONG_DISTRICT			VARCHAR(100),
	ADMIN_ADD_REMARKS 			TEXT,
	ADMIN_ASSISTANCE_TYPE		VARCHAR(50)			CHECK (ADMIN_ASSISTANCE_TYPE IN ('Regular', 'Merit-Based', 'PDAF'))		NOT NULL	
);

CREATE TABLE APPLICANT (
	APP_CODE					SERIAL										PRIMARY KEY,
	APP_LAST_NAME				VARCHAR(50)									NOT NULL,
	APP_FIRST_NAME				VARCHAR(50)									NOT NULL,
	APP_MIDDLE_INITIAL			CHAR(1),
	APP_DATE_OF_BIRTH			DATE										NOT NULL,
	APP_PLACE_OF_BIRTH			VARCHAR(255)								NOT NULL,
	APP_GENDER					VARCHAR(15)			CHECK (APP_GENDER IN ('Male', 'Female', 'Other')),
	APP_CIVIL_STATUS			VARCHAR(15),
	APP_ETHNO_GROUP				VARCHAR(50)									NOT NULL,
	APP_PHONE_NUMBER			VARCHAR(15),
	APP_EMAIL					VARCHAR(50)			UNIQUE,
	APP_FORM_SIGNED				BOOLEAN										NOT NULL,	
    APP_SIGNATURE_DATE			DATE				DEFAULT CURRENT_DATE	NOT NULL,
	APP_DELETED_AT 				TIMESTAMPTZ,
	APP_DELETED_BY 				INTEGER 									REFERENCES USERS(USER_ID),
	ADMIN_CODE					INT											NOT NULL REFERENCES ADMINISTRATOR (ADMIN_CODE) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE SIGNATURE_SCAN (
    SCAN_ID 					SERIAL 									PRIMARY KEY,
    SCAN_FILENAME 				VARCHAR(255) 							NOT NULL,
    SCAN_FILETYPE 				VARCHAR(50) 							NOT NULL,
    SCAN_DATA 					BYTEA 									NOT NULL, 
	SCAN_UPLOAD_DATE 			TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    APP_CODE 					INT 									NOT NULL REFERENCES APPLICANT(APP_CODE) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE ADDRESS (
	ADDR_CODE					SERIAL				PRIMARY KEY,
	ADDR_MAIL					VARCHAR(255),
	ADDR_PERMANENT				VARCHAR(255)		NOT NULL,
	ADDR_HOUSE_AND_STREET		VARCHAR(100)		NOT NULL,
	ADDR_BARANGAY				VARCHAR(50)			NOT NULL,
	ADDR_MUNICIPALITY			VARCHAR(20)			NOT NULL,
	ADDR_PROVINCE				VARCHAR(20)			NOT NULL,
	APP_CODE					INT					NOT NULL REFERENCES APPLICANT (APP_CODE) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE EDUCATION_CATEGORY (
    EDUC_ID     SERIAL          PRIMARY KEY,
    EDUC_NAME   VARCHAR(50)     NOT NULL
);

INSERT INTO EDUCATION_CATEGORY (EDUC_NAME) VALUES 
('Elementary'),
('High School'),
('Vocational'),
('College/Undergraduate'),
('Post-Graduate'),
('Masteral'),
('Doctorate');

CREATE TABLE EDUCATION_ATTAINMENT (
	EDUA_CODE					SERIAL				PRIMARY KEY,
	EDUC_ID 					INT					REFERENCES EDUCATION_CATEGORY (EDUC_ID) ON DELETE CASCADE ON UPDATE CASCADE,
	APP_CODE					INT					REFERENCES APPLICANT (APP_CODE) ON DELETE CASCADE ON UPDATE CASCADE,
	UNIQUE (APP_CODE, EDUC_ID)
);

CREATE TABLE SCHOOL (
	SCH_CODE					SERIAL				PRIMARY KEY,
	SCH_NAME					VARCHAR(100)		NOT NULL,
	SCH_ADDRESS    				VARCHAR(255),
	SCH_YEAR_GRAD				SMALLINT			NOT NULL CHECK (SCH_YEAR_GRAD >= 1900 AND SCH_YEAR_GRAD <= EXTRACT(YEAR FROM NOW())),
	SCH_GRADE_AVG				DECIMAL(5, 2)		NOT NULL,
	SCH_RANK					VARCHAR(20),
	EDUA_CODE					INT					NOT NULL REFERENCES EDUCATION_ATTAINMENT (EDUA_CODE) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE FAMILY_BACKGROUND (
	FAM_CODE					SERIAL				PRIMARY KEY,
	FAM_RELATION				VARCHAR(10)			NOT NULL CHECK (FAM_RELATION IN ('Father', 'Mother', 'Guardian')),
	FAM_IS_ALIVE				BOOLEAN				NOT NULL,
	FAM_NAME					VARCHAR(50)			NOT NULL,
	FAM_ADDR					VARCHAR(255)		NOT NULL,
	FAM_OCCUP					VARCHAR(50),
	FAM_OFFICE_ADDR				VARCHAR(50),
	FAM_EDUC_ATTAIN				VARCHAR(50),
	FAM_ETHNO_GROUP				VARCHAR(50)			NOT NULL,
	FAM_ANNUAL_INCOME			DECIMAL(11, 2)		NOT NULL,
	FAM_YEAR_ITR				SMALLINT			NOT NULL CHECK (FAM_YEAR_ITR >= 1900 AND FAM_YEAR_ITR <= EXTRACT(YEAR FROM NOW())),
	APP_CODE					INT					NOT NULL REFERENCES APPLICANT (APP_CODE) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE SIBLING (
	SIB_CODE					SERIAL 				PRIMARY KEY,
	SIB_LAST_NAME				VARCHAR(50)			NOT NULL,
	SIB_FIRST_NAME				VARCHAR(50)			NOT NULL,
	SIB_MIDDLE_INITIAL			CHAR(1),
	APP_CODE					INT					NOT NULL REFERENCES APPLICANT (APP_CODE) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE SCHOLARSHIP (
	SCHOLAR_CODE				SERIAL				PRIMARY KEY,
	SCHOLAR_NAME				VARCHAR(50)			NOT NULL,
	SCHOLAR_COURSE				VARCHAR(50),
	SCHOLAR_YEAR				VARCHAR(20),
	SCHOLAR_DELETED_AT 			TIMESTAMPTZ,
	SCHOLAR_DELETED_BY 			INTEGER REFERENCES USERS(USER_ID),
	SIB_CODE					INT					NOT NULL REFERENCES SIBLING (SIB_CODE) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE SCHOOL_PREFERENCE (
	PREF_CODE					SERIAL 				PRIMARY KEY,
	PREF_NAME					VARCHAR(50)			NOT NULL,
	PREF_ADDR					VARCHAR(50)			NOT NULL,
	PREF_DEGREE					VARCHAR(50)			NOT NULL,
	PREF_REMARKS				TEXT,
	APP_CODE					INT					NOT NULL REFERENCES APPLICANT (APP_CODE) ON DELETE CASCADE ON UPDATE CASCADE
);


CREATE TABLE ANNOUNCEMENT (
    ANN_ID 				SERIAL 											PRIMARY KEY,
    ANN_TITLE 			VARCHAR(255) 									NOT NULL,
    ANN_CONTENT 		TEXT,
    ANN_DATE 			DATE				DEFAULT CURRENT_DATE,
	ANN_DELETED_AT 		TIMESTAMPTZ,
	ANN_DELETED_BY 		INTEGER REFERENCES USERS(USER_ID)
);

CREATE TABLE COMMENT (
    COMS_ID             		SERIAL          	PRIMARY KEY,
    COMS_GOOGLE_NAME    		VARCHAR(255)    	NOT NULL,
    COMS_GOOGLE_EMAIL   		VARCHAR(255)    	NOT NULL,
    COMS_GOOGLE_PIC     		VARCHAR(255),
    COMS_CONTENT        		TEXT            	NOT NULL,
    COMS_CREATED_AT     		TIMESTAMP       	DEFAULT CURRENT_TIMESTAMP,
	ANN_ID 						INT            		NOT NULL REFERENCES ANNOUNCEMENT (ANN_ID) ON DELETE CASCADE ON UPDATE CASCADE,
	COMS_PARENT_ID      		INT 				REFERENCES COMMENT (COMS_ID) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE ANNOUNCEMENT_VIEW (
    ANNV_ID             		SERIAL          PRIMARY KEY,
    ANNV_COUNT          		INT             DEFAULT 1,
    ANNV_LAST_UPDATED        	TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
	ANN_ID     					INT             NOT NULL REFERENCES ANNOUNCEMENT (ANN_ID) ON DELETE CASCADE ON UPDATE CASCADE
);

INSERT INTO ANNOUNCEMENT (ANN_TITLE, ANN_CONTENT, ANN_DATE) VALUES
-- 1
('Advisory', 'On April 16, 2025, the National Commission on Indigenous Peoples – Central Office and Regional Offices will implement a work-from-home...', '2025-4-14'),
-- 2
('Makasaysayang araw para sa mga Eskaya!', 'Sinaksihan ni National Commission on Indigenous Peoples Chairperson Jennifer Pia Sibug-Las ang pormal na pagkakaloob ng Certificate of Recognition of...', '2025-3-28'),
-- 3
('NCIP, nakiisa sa forum para sa kababaihan mula sa ibat ibang sektor', 'Nakiisa ang National Commission on Indigenous Peoples (NCIP) sa 2025 Philippine Open Government Partnership (PH-OGP) National Women''s Month Forum na...', '2025-3-27'),
-- 4
('NCIP Dumalo sa National Women Leaders’ Summit on Halal Industry Advancement', 'Dumalo bilang panauhin at resource speaker si NCIP Chairperson Jennifer Pia Sibug-Las sa kauna-unahang National Women Leaders’ Summit on Halal...', '2025-3-25'),
-- 5
('Workshop on the Identification of Partnership Entry Points between the Commission on Population and Development (CPD) and the National Commission on Indigenous Peoples', 'The CPD hosted a workshop at the Sotogrande Hotel in Quezon City on March 24, 2025, inviting the NCIP to...', '2025-3-25'),
-- 6
('NCIP Chairperson Sibug-Las, Tagapanayam sa Women in Extractives Forum 2025', 'Nagsilbing tagapanayam si National Commission on Indigenous Peoples (NCIP) Chairperson Jennifer Pia Sibug-Las sa isang pagtitipon ng mga kababaihan sa...', '2025-3-18'),
-- 7
('NCIP Chairperson Jennifer Pia Sibug-Las ginawaran bilang Natatanging Lingkod Bayan sa Gawad Pilipino Awards 2025', 'Ginawaran si NCIP Chairperson Jennifer Pia Sibug-Las bilang Natatanging Lingkod Bayan sa Gawad Pilipino Awards 2025 na ginanap sa AFPCOC,...', '2025-3-17'),
-- 8
('Walong Katutubong Biktima ng Human Trafficking, niligtas ng NCIP, DMW, DFA, Embahada ng Pilipinas sa Kuala Lumpur, at DSWD', 'Ang National Commission on Indigenous Peoples (NCIP) ay mariing nagpapayo at nagpapaalala sa mga Katutubong Pamayanan na maging masigasig sa...', '2025-3-15'),
-- 9
('NCIP, Pinarangalan ng Gawad Bayanuhan sa Pamumuhunan', 'Kinilala ang National Commission on Indigenous Peoples (NCIP) at si Chairperson Jennifer Pia Sibug-Las sa Gawad Bayanihan sa Pamumuhunan Awarding...', '2025-3-13'),
-- 10
('NCIP Published its 3rd Volume of NCIP IndigeNews', 'reserving cultures and breaking barriers Celebrate the strength and wit of Indigenous women with us through the 3rd Volume of...', '2025-3-12'),
-- 11
('NCIP, OES Nagpulong Para sa Kapakanan ng mga Katutubo', 'Lunes, 10 Marso 2025, nakipagpulong ang National Commission on Indigenous Peoples (NCIP) sa Office of the Executive Secretary (OES) upang...', '2025-3-11'),
-- 12
('Philippines-United Nations (UN) Dialogue 2025: Driving Transitions for Impact, Scale, and Synergy', '05 March 2025 - As this year marks the 80th anniversary of the United Nations, the Philippines and the UN...', '2025-3-05'),
-- 13
('Kababaihang Katutubo, Bida sa Women’s Month Exhibit ng NCIP', 'Nakiisa ang National Commission on Indigenous Peoples (NCIP) sa seremonyal na pagsisimula ng National Women’s Month Celebration (NWMC) ngayong araw,...', '2025-3-05'),
-- 14
('Partnerships sealed to uphold Indigenous Rights and Environmental Stewardship', 'In celebration of World Wildlife Day 2025, the National Commission on Indigenous Peoples (NCIP) signed partnerships that will safeguard the...', '2025-3-03'),
-- 15
('NCIP CONDEMNS NON-MORO INDIGENOUS PEOPLES KILLINGS IN MAGUINDANAO DEL SUR', 'It has come to the National Commission on Indigenous Peoples’ (NCIP) knowledge that Timuay Renato Promboy, a well-known Teduray Indigenous...', '2025-2-27');

CREATE TABLE CONTACT_MESSAGE (
    CONTM_ID                SERIAL          PRIMARY KEY,
    CONTM_NAME              VARCHAR(100)    NOT NULL,
    CONTM_EMAIL             VARCHAR(100)    NOT NULL,
    CONTM_PHONE             VARCHAR(20),
    CONTM_MESSAGE           TEXT            NOT NULL,
    CONTM_SUBMISSION_DATE   TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    CONTM_STATUS            VARCHAR(20)     DEFAULT 'Unread',
	CONTM_DELETED_AT 		 TIMESTAMPTZ,
	CONTM_DELETED_BY 		 INTEGER 		REFERENCES USERS(USER_ID)
);


CREATE INDEX IF NOT EXISTS idx_contact_email ON contact_message(CONTM_EMAIL);
CREATE INDEX IF NOT EXISTS idx_contact_status ON contact_message(CONTM_STATUS);
CREATE INDEX IF NOT EXISTS idx_contact_date ON contact_message(CONTM_SUBMISSION_DATE );

CREATE TABLE population_table (
    POPT_ID 				SERIAL 			PRIMARY KEY,
    POPT_NAME 				VARCHAR(100),
    POPT_IP_GROUP 			VARCHAR(50),
    POPT_LATITUDE 			NUMERIC(9,6),
    POPT_LONGITUDE 			NUMERIC(9,6),
    POPT_POPULATION 		INTEGER,
	POPT_DELETED_AT 		TIMESTAMPTZ,
	POPT_DELETED_BY 		INTEGER 		REFERENCES USERS(USER_ID)
);

INSERT INTO population_table (POPT_NAME, POPT_IP_GROUP, POPT_LATITUDE, POPT_LONGITUDE, POPT_POPULATION) VALUES
('Sitio Naba Alaska, Brgy. Mambaling, Cebu City', 'BADJAO', 10.289915, 123.881888, 776),
('Sitio Puntod, Brgy. Mambaling, Cebu City', 'BADJAO', 10.285021, 123.885158, 651),
('Brgy. Dolho, Bato, Leyte', 'BADJAO', 10.3328, 124.7865, 805),
('Brgy. Marvel, Municipality of Isabel, Leyte', 'BADJAO', 10.9337, 124.4343, 209),
('Sitio Buyo, Brgy. South Poblacion, Naga City, Cebu', 'ATI', 10.205323, 123.75804, 214),
('Brgy. Kagbana, Burauen, Leyte', 'MAMANWA', 10.8313, 124.8383, 61),
('Brgy. Bunga, Baybay City, Leyte', 'MAMANWA', 10.7791, 124.7821, 10),
('Sitio Palayan, Brgy. Caucab, Almeria, Biliran', 'MAMANWA', 11.660135, 124.416859, 60),
('Brgy. Malinao, St. Bernard, Southern Leyte', 'MAMANWA', 10.3618, 125.0967, 179),
('Sitio Wespal, Brgy. Guirang, Basey, Western Samar', 'MAMANWA', 11.342906, 125.151883, 94),
('Brgy. Pinamudlan, San Francisco, Southern Leyte', 'MAMANWA', 10.040500, 	125.206000, 70);


CREATE TABLE USER_LOGS (
    LOG_ID SERIAL PRIMARY KEY,
    USER_ID INTEGER REFERENCES USERS(USER_ID),
    LOG_TYPE VARCHAR(50) NOT NULL, 
    LOG_DETAILS TEXT NULL,
    LOG_TIMESTAMP TIMESTAMPTZ  DEFAULT CURRENT_TIMESTAMP NOT NULL,
    LOG_IP_ADDRESS VARCHAR(45) NULL -- Optional: store IP address
);

-- Index for performance
CREATE INDEX IF NOT EXISTS idx_logs_user_id ON USER_LOGS(USER_ID);
CREATE INDEX IF NOT EXISTS idx_logs_type ON USER_LOGS(LOG_TYPE);
CREATE INDEX IF NOT EXISTS idx_logs_timestamp ON USER_LOGS(LOG_TIMESTAMP);

CREATE TABLE SCHOLARSHIP_STATUS (
    STATUS_ID SERIAL PRIMARY KEY,
	STATUS_DELETED_AT TIMESTAMPTZ,
	STATUS_DELETED_BY INTEGER REFERENCES USERS(USER_ID),
    APP_CODE INT NOT NULL REFERENCES APPLICANT(APP_CODE) ON DELETE CASCADE ON UPDATE CASCADE,
    STATUS_CODE VARCHAR(20) NOT NULL CHECK (STATUS_CODE IN ('PENDING', 'APPROVED', 'REJECTED')),
    STATUS_DATE TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    STATUS_IS_ACTIVE BOOLEAN NOT NULL DEFAULT TRUE,
    STATUS_REMARKS TEXT
);



-- Add indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_scholarship_status_app ON SCHOLARSHIP_STATUS(APP_CODE);
CREATE INDEX IF NOT EXISTS idx_scholarship_status_active ON SCHOLARSHIP_STATUS(STATUS_IS_ACTIVE);
CREATE INDEX IF NOT EXISTS idx_scholarship_status_code ON SCHOLARSHIP_STATUS(STATUS_CODE);

CREATE TABLE RECYCLE_BIN (
    REC_ID SERIAL PRIMARY KEY,
    REC_ORIGINAL_TABLE VARCHAR(100) NOT NULL,
    REC_ORIGINAL_ID INTEGER NOT NULL,
    REC_ITEM_DATA JSONB NOT NULL,
    REC_DELETED_BY INTEGER REFERENCES users(user_id),
    REC_DELETED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    REC_ITEM_TYPE VARCHAR(50) NOT NULL,
    REC_ITEM_TITLE VARCHAR(255),
    REC_RESTORE_PATH VARCHAR(255)
);


-- Add indexes for performance
CREATE INDEX IF NOT EXISTS idx_recycle_bin_table ON recycle_bin(REC_ORIGINAL_TABLE);
CREATE INDEX IF NOT EXISTS idx_recycle_bin_deleted_at ON recycle_bin(REC_DELETED_AT);
CREATE INDEX IF NOT EXISTS idx_recycle_bin_type ON recycle_bin(REC_ITEM_TYPE);
CREATE INDEX IF NOT EXISTS idx_users_deleted_at ON USERS(USER_DELETED_AT);
CREATE INDEX IF NOT EXISTS idx_applicant_deleted_at ON APPLICANT(APP_DELETED_AT);
CREATE INDEX IF NOT EXISTS idx_contact_message_deleted_at ON CONTACT_MESSAGE(CONTM_DELETED_AT);
CREATE INDEX IF NOT EXISTS idx_announcement_deleted_at ON ANNOUNCEMENT(ANN_DELETED_AT);
CREATE INDEX IF NOT EXISTS idx_population_deleted_at ON POPULATION_TABLE(POPT_DELETED_AT);
CREATE INDEX IF NOT EXISTS idx_scholarship_deleted_at ON SCHOLARSHIP(SCHOLAR_DELETED_AT);
CREATE INDEX IF NOT EXISTS idx_scholarship_status_deleted_at ON SCHOLARSHIP_STATUS(STATUS_DELETED_AT);

SELECT * FROM ADDRESS;
SELECT * FROM ADMINISTRATOR;
SELECT * FROM ANNOUNCEMENT_VIEW;
SELECT * FROM ANNOUNCEMENT;
SELECT * FROM COMMENT;
SELECT * FROM CONTACT_MESSAGE;
SELECT * FROM EDUCATION_ATTAINMENT;
SELECT * FROM EDUCATION_CATEGORY;
SELECT * FROM FAMILY_BACKGROUND;
SELECT * FROM SCHOLARSHIP;
SELECT * FROM SCHOLARSHIP_STATUS;
SELECT * FROM SCHOOL;
SELECT * FROM SCHOOL_PREFERENCE;
SELECT * FROM SIBLING;
SELECT * FROM SIGNATURE_SCANS;
SELECT * FROM USERS;
SELECT * FROM POPULATION_TABLE;
SELECT * FROM USER_LOGS;

