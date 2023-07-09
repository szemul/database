# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [3.1.0] - 2023-07-09

### Added

- `\Szemul\Database\Exception\EntityNotFoundException` Moved from `szemul/framework`
- `\Szemul\Database\Dao\DaoAbstract` Moved from `szemul/framework`

## [3.0.2] - 2023-07-02

### Added
- `\Szemul\Database\Helper\QueryHelper::getEqualityCondition` allows != as well as =


## [3.0.1] - 2023-02-11

### Added
- `\Szemul\Database\Helper\QueryHelper::getInListCondition` handles backed enum list

### Changed

- `\Szemul\Database\Helper\QueryHelper::getListFromTableByIds` now throws Exception if no fields passed
- Name of the id field can be passed to `\Szemul\Database\Helper\QueryHelper::getListFromTableByIds`

## [3.0.0] - 2022-10-03

Use the `\Szemul\Database\Connection\ConnectionFactory::getMysql` method to instantiate the MysqlConnection class.

### Added

- \Szemul\Database\Connection\ConnectionFactory to help create connection classes.
- `EntityDuplicateException` used to inform about a unique key violation
- `ServerHasGoneAwayExcetion` used to inform about a connection timeout
- `UserDefinedExcetion` used to inform about an error defined in the SQL server by a user (triggers for example)
- This changelog :)

### Changed

- `\Szemul\Database\Connection\MysqlConnection::query` is now processing the received exception and translates them to
  more specific exceptions if possible.

### Fixed

- `\Szemul\Database\Connection\MysqlConnection::query` method was repeating the query in case of an error. This has been
  fixed.
