# WP2 Scaffolding Framework

The WP2 Scaffolding Framework is a robust solution for rapidly generating components and extending WordPress functionality. It provides a structured approach to create themes, plugins, and other WordPress assets with minimal effort, leveraging predefined templates, services, and utilities.

## Directory Structure Overview

### Root-Level Files

- CONTRIBUTING.md: Guidelines for contributing to this repository.
- README.md: This documentation file.
- composer.json: Composer configuration for dependency management and autoloading.

### Key Directories

#### 1. Generators

The Generators directory contains logic for generating specific WordPress components. Each subdirectory focuses on a particular category of assets:

Assets: Generates assets like fonts, icons, scripts, and styles.
Example Generators: AssetGenerator.php

Blocks: Handles block generation for the Blockstudio ecosystem.
Example Generator: BlockGenerator.php

Connections: Facilitates API integrations, such as OAuth2 or HeaderBearer authentication.
Example Generators: OAuth2ConnectionGenerator.php, HeaderBearerConnectionGenerator.php

Extensions: Generates WordPress extensions for platforms like Coda, Expo, or Plasmo.
Example Generators: ExtensionGenerator.php, CodaExtensionGenerator.php

Files: Provides utilities for file generation and manipulation.
Example: FileGenerator.php

Pages: Automates the creation of WordPress pages.
Example: StandardPageGenerator.php

Plugins: Creates different types of plugins (standard, must-use, and extended).
Example Generators: PluginGenerator.php, MustUsePluginGenerator.php

Themes: Scaffolds themes and their components, such as template parts, settings, and patterns.
Example Generators: ThemeGenerator.php, TemplatePartGenerator.php

Each generator is tailored to streamline development for its respective category.

#### 2. Services

The Services directory provides reusable utilities for managing operations like file I/O and configuration. Key services include:
- ConfigService.php: Handles configuration settings for generators.
- DirectoryService.php: Manages directory creation and validation.
- TemplateService.php: Processes template files for the Templates directory.
- GeneratorService.php: Coordinates generator execution.

#### 3. Templates

This directory houses predefined templates that generators use to create standardized WordPress components. Templates are defined in .tpl files and mapped to their respective generators:
- Blocks: index.php.tpl
- Connections: HeaderBearerConnection.php.tpl, OAuth2Connection.php.tpl
- Extensions: Templates for Coda, Expo, and Plasmo integrations.
- Themes: Includes template files for colors, font families, layouts, and more.

#### 4. Utils

Reusable utility classes for common operations:
- Logger.php: Provides logging capabilities.
- Validator.php: Ensures input validation.

#### 5. Tests

The tests directory includes PHPUnit test cases for validating generators, services, and utilities:
- Generators: Tests for all major generator classes (e.g., ThemeGeneratorTest.php).
- Services: Tests for shared services (e.g., ConfigServiceTest.php).
- Utils: Tests for utility classes (e.g., LoggerTest.php).

## Usage

### Setup

1. Install dependencies using Composer:

2. Run composer dump-autoload to ensure autoloading works correctly.

### Generate a Component

To scaffold a new component, instantiate the appropriate generator class and provide the necessary input.

### Run Tests

Execute PHPUnit to validate your setup:

vendor/bin/phpunit

## Contribution Guidelines

- RFollow PSR-4 autoloading standards.
- RMaintain separation of concerns within generators, services, and utilities.
- RWrite unit tests for all new classes under the tests directory.
- Refer to CONTRIBUTING.md for more details.

## License

This project is open-source and distributed under the MIT License. See the LICENSE file for details.