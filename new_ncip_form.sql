CREATE TABLE APPLICANT (
	APP_CODE					SERIAL				                                                                                    PRIMARY KEY,
	APP_LAST_NAME				VARCHAR(50)			                                                                                    NOT NULL,
	APP_FIRST_NAME				VARCHAR(50)			                                                                                    NOT NULL,
	APP_MIDDLE_NAME				CHAR(1),
	APP_DOB						DATE				                                                                                    NOT NULL,
	APP_POB						VARCHAR(50)			                                                                                    NOT NULL,
    APP_EMAIL	                VARCHAR(50)         UNIQUE                                                                              NOT NULL,
	ADMIN_CODE					INT					                                                                                    REFERENCES ADMINISTRATOR (ADMIN_CODE) ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE (APP_LAST_NAME, 	APP_FIRST_NAME)
);

CREATE TABLE ETHNO_GROUP (
    ETHNO_GROUP_ID               SERIAL                                                                                                 PRIMARY KEY,
    ETHNO_NAME                   VARCHAR(50)                                                                                            NOT NULL,
    APP_CODE                     INT                                                                                                    REFERENCES APPLICANT (APP_CODE) ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE (ETHNO_NAME, APP_CODE)
);

CREATE TABLE CIVIL_STATUS (
    CIVIL_STATUS_ID            SERIAL                                                                                                   PRIMARY KEY,
    CIVIL_STATUS_NAME          VARCHAR(15)                                                                                              NOT NULL,
    APP_CODE                   INT                                                                                                      REFERENCES APPLICANT (APP_CODE) ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE (CIVIL_STATUS_NAME, APP_CODE)
);

CREATE TABLE APPLICANT_PHONE (
    PHONE_ID                    SERIAL                                                                                                  PRIMARY KEY,
    PHONE_NUMBER                VARCHAR(15)                                                                                             NOT NULL,
    APP_CODE                    INT                                                                                                     REFERENCES APPLICANT (APP_CODE) ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE (PHONE_NUMBER, APP_CODE)
);

CREATE TABLE GENDER (   
    GENDER_ID                   SERIAL                                                                                                  PRIMARY KEY,
    GENDER_TYPE                 VARCHAR(15)	        CHECK (APP_GENDER IN ('Male', 'Female', 'Lesbian', 'Gay', 'Bisexual', 'Other'))     NOT NULL,
    APP_CODE                    INT                                                                                                     REFERENCES APPLICANT (APP_CODE) ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE (GENDER_TYPE, APP_CODE)
);

CREATE TABLE ADMINISTRATOR (
    ADMIN_CODE                  SERIAL                                                                                                  PRIMARY KEY,
    ADMIN_APP_NUMBER            INT                                                                                                     NOT NULL,
    ADMIN_DOF                   DATE                                                                                                    NOT NULL,
    ADMIN_ADD_REMARKS           TEXT,
    ADMIN_ASSISTANCE_TYPE       VARCHAR(20)         CHECK (ADMIN_ASSISTANCE_TYPE IN ('Regular', 'Merit-Based'))                         NOT NULL
);

CREATE TABLE ADMIN_ACTIONS (
    ACTION_ID                   SERIAL                                                                                                  PRIMARY KEY,
    ADMIN_ACTION_TAKEN_CSC_PO	VARCHAR(50),
	ADMIN_ACTION_TAKEN_RO		VARCHAR(50),
    ADMIN_CODE                  INT                                                                                                     REFERENCES ADMINISTRATOR (ADMIN_CODE) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE ADMIN_LOCATION (
    LOCATION_ID             SERIAL                                                                                                      PRIMARY KEY,
    ADMIN_CADT              VARCHAR(50)                                                                                                 NOT NULL,
    ADMIN_CONG_DISTRICT     VARCHAR(50)                                                                                                 NOT NULL,
    ADMIN_CODE              INT                                                                                                         REFERENCES ADMINISTRATOR (ADMIN_CODE) ON UPDATE CASCADE ON DELETE CASCADE 
);

CREATE TABLE ADDRESS (
    ADDR_CODE              SERIAL                                                                                                       PRIMARY KEY,
    ADDR_TYPE              VARCHAR(20)              CHECK (ADDR_TYPE IN ('Mailing', 'Permanent'))                                       NOT NULL,
    HOUSE_AND_STREET       VARCHAR(70)                                                                                                  NOT NULL,
    BARANGAY_ID            INT                                                                                                          REFERENCES BARANGAY (BARANGAY_ID) ON UPDATE CASCADE ON DELETE CASCADE,
    APP_CODE               INT                                                                                                          REFERENCES APPLICANT (APP_CODE) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE PROVINCE (
    PROVINCE_ID            SERIAL                                                                                                       PRIMARY KEY,
    PROVINCE_NAME          VARCHAR(50)              UNIQUE                                                                              NOT NULL
);

CREATE TABLE MUNICIPALITY (
    MUNICIPALITY_ID        SERIAL                                                                                                       PRIMARY KEY,
    MUNICIPALITY_NAME      VARCHAR(50)                                                                                                  NOT NULL,
    PROVINCE_ID            INT                                                                                                          REFERENCES PROVINCE (PROVINCE_ID) ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE (MUNICIPALITY_NAME, PROVINCE_ID)
);

CREATE TABLE BARANGAY (
    BARANGAY_ID            SERIAL                                                                                                       PRIMARY KEY,
    BARANGAY_NAME          VARCHAR(50)                                                                                                  NOT NULL,
    MUNICIPALITY_ID        INT                                                                                                          REFERENCES MUNICIPALITY (MUNICIPALITY_ID) ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE (BARANGAY_NAME, MUNICIPALITY_ID)
);

CREATE TABLE EDUCATION_ATTAINMENT (
	EDUC_CODE					SERIAL				                                                                                    PRIMARY KEY,
	EDUC_LEVEL					VARCHAR(20)			                                                                                    NOT NULL,
	APP_CODE					INT					                                                                                    REFERENCES APPLICANT (APP_CODE) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE SCHOOL (
	SCH_CODE					SERIAL				                                                                                    PRIMARY KEY,
	SCH_NAME					VARCHAR(100)		                                                                                    NOT NULL,
	SCH_YEAR_GRAD				SMALLINT		    CHECK (SCH_YEAR_GRAD >= 1900 AND SCH_YEAR_GRAD <= EXTRACT(YEAR FROM NOW()))	        NOT NULL,
	SCH_GRADE_AVG				DECIMAL(5, 2)		                                                                                    NOT NULL,
	SCH_RANK					VARCHAR(20),
	EDUC_CODE					INT					                                                                                    REFERENCES EDUCATION_ATTAINMENT (EDUC_CODE) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE FAMILY_BACKGROUND (
	FAM_CODE					SERIAL				                                                                                    PRIMARY KEY,
	FAM_RELATION				VARCHAR(10)			CHECK (FAM_RELATION IN ('Father', 'Mother', 'Guardian'))                            NOT NULL,
	FAM_IS_ALIVE				BOOLEAN				                                                                                    NOT NULL,
	FAM_NAME					VARCHAR(50)			                                                                                    NOT NULL,
	FAM_ADDR					VARCHAR(50)			                                                                                    NOT NULL,
	FAM_OCCUP					VARCHAR(50),
	FAM_OFFICE_ADDR				VARCHAR(50),
	FAM_EDUC_ATTAIN				VARCHAR(50),
	FAM_ETHNO_GROUP				VARCHAR(50)			                                                                                    NOT NULL,
	FAM_ANNUAL_INCOME			DECIMAL(11, 2)		                                                                                    NOT NULL,
	FAM_YEAR_ITR				SMALLINT			CHECK (FAM_YEAR_ITR >= 1900 AND FAM_YEAR_ITR <= EXTRACT(YEAR FROM NOW()))           NOT NULL,
    FAM_NO_SIBLINGS             INT,
	APP_CODE					INT					                                                                                    REFERENCES APPLICANT (APP_CODE) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE SIBLING (
	SIB_CODE					SERIAL 				                                                                                    PRIMARY KEY,
	SIB_LAST_NAME				VARCHAR(50)			                                                                                    NOT NULL,
	SIB_FIRST_NAME				VARCHAR(50)			                                                                                    NOT NULL,
	SIB_MIDDLE_NAME				CHAR(1),
	APP_CODE					INT					                                                                                    REFERENCES APPLICANT (APP_CODE) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE SIBLING_SCHOLARSIP (
	SCHOLAR_CODE				SERIAL				                                                                                    PRIMARY KEY,
	SCHOLAR_NAME				VARCHAR(50)			                                                                                    NOT NULL,
	SCHOLAR_COURSE				VARCHAR(50),
	SCHOLAR_YEAR				SMALLINT,
	SIB_CODE					INT					                                                                                    REFERENCES SIBLING (SIB_CODE) ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE SCHOOL_PREFERENCE (
	PREF_CODE					SERIAL 				                                                                                    PRIMARY KEY,
	PREF_NAME					VARCHAR(50)			                                                                                    NOT NULL,
	PREF_ADDR					VARCHAR(50)			                                                                                    NOT NULL,
	PREF_DEGREE					VARCHAR(50)			                                                                                    NOT NULL,
	PREF_REMARKS				TEXT,
	APP_CODE					INT					                                                                                    REFERENCES APPLICANT (APP_CODE) ON UPDATE CASCADE ON DELETE CASCADE
);