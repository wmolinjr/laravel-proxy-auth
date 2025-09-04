# Contributing to Laravel OAuth2/OIDC Identity Provider

Thank you for your interest in contributing to the Laravel OAuth2/OIDC Identity Provider! This document provides guidelines and information for contributors.

## 🤝 Ways to Contribute

- **Bug Reports**: Submit detailed bug reports with reproduction steps
- **Feature Requests**: Propose new features or improvements
- **Code Contributions**: Submit pull requests with bug fixes or new features
- **Documentation**: Improve documentation, examples, or tutorials
- **Testing**: Help test new features and report issues
- **Community Support**: Help answer questions and support other users

## 🚀 Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer 2.0+
- Node.js 20.x+
- Database (MySQL 8.0+, PostgreSQL 13+, or SQLite)
- Redis (optional, for caching and queues)

### Development Setup

1. **Fork and Clone**
   ```bash
   git clone https://github.com/your-username/laravel-oauth-provider.git
   cd laravel-oauth-provider
   ```

2. **Install Dependencies**
   ```bash
   # Install PHP dependencies
   composer install
   
   # Install Node.js dependencies
   npm install
   ```

3. **Environment Configuration**
   ```bash
   # Copy environment file
   cp .env.example .env
   
   # Generate application key
   php artisan key:generate
   
   # Generate OAuth keys
   php artisan oauth:keys
   ```

4. **Database Setup**
   ```bash
   # Configure database in .env, then run:
   php artisan migrate
   php artisan db:seed
   ```

5. **Build Assets**
   ```bash
   # Development build
   npm run dev
   
   # Production build
   npm run build
   ```

6. **Start Development Server**
   ```bash
   # Start all services
   composer run dev
   
   # Or start individually
   php artisan serve
   php artisan queue:work
   npm run dev
   ```

### Using Docker

```bash
# Build and start containers
docker-compose up -d

# Install dependencies
docker-compose exec app composer install
docker-compose exec app npm install

# Setup application
docker-compose exec app php artisan migrate
docker-compose exec app php artisan oauth:keys
docker-compose exec app npm run build
```

## 📋 Development Guidelines

### Code Style

We follow PSR-12 coding standards and additional conventions:

- **PHP**: Follow PSR-12 with Laravel conventions
- **JavaScript/TypeScript**: Use ESLint and Prettier configurations
- **CSS**: Follow BEM methodology for class naming
- **Comments**: Write meaningful comments for complex logic
- **Naming**: Use descriptive names for variables and functions

### Code Formatting

```bash
# Format PHP code
composer run format

# Format JavaScript/TypeScript
npm run format

# Check code style
composer run lint
npm run lint
```

### Testing

All contributions must include appropriate tests:

```bash
# Run all tests
php artisan test

# Run specific test suites
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run tests with coverage
php artisan test --coverage

# Run JavaScript tests
npm test

# Run E2E tests
npm run test:e2e
```

### Test Guidelines

- **Unit Tests**: Test individual methods and classes
- **Feature Tests**: Test complete features and workflows
- **Integration Tests**: Test OAuth flows end-to-end
- **Security Tests**: Test security features and vulnerabilities
- **Performance Tests**: Test response times and resource usage

## 🔄 Pull Request Process

### Before Submitting

1. **Create Feature Branch**
   ```bash
   git checkout -b feature/your-feature-name
   # or
   git checkout -b fix/your-bug-fix
   ```

2. **Make Changes**
   - Write clean, documented code
   - Add tests for new functionality
   - Update documentation if needed
   - Follow coding standards

3. **Test Your Changes**
   ```bash
   # Run full test suite
   composer run test
   npm run test
   
   # Check code quality
   composer run lint
   npm run lint
   
   # Run security checks
   composer audit
   npm audit
   ```

4. **Commit Your Changes**
   ```bash
   git add .
   git commit -m "feat: add user profile management feature"
   ```

### Commit Message Convention

We use [Conventional Commits](https://conventionalcommits.org/):

- `feat:` - New features
- `fix:` - Bug fixes
- `docs:` - Documentation changes
- `style:` - Code style changes (formatting, etc.)
- `refactor:` - Code refactoring
- `test:` - Adding or updating tests
- `chore:` - Maintenance tasks

Examples:
```
feat: add PKCE support for public clients
fix: resolve token refresh race condition
docs: update OAuth2 setup guide
test: add integration tests for client credentials flow
```

### Submitting Pull Request

1. **Push to Your Fork**
   ```bash
   git push origin feature/your-feature-name
   ```

2. **Create Pull Request**
   - Use the PR template
   - Write clear title and description
   - Reference related issues
   - Add screenshots if applicable

3. **PR Template**
   ```markdown
   ## Description
   Brief description of the changes.
   
   ## Type of Change
   - [ ] Bug fix (non-breaking change)
   - [ ] New feature (non-breaking change)
   - [ ] Breaking change (fix or feature that causes existing functionality to not work as expected)
   - [ ] Documentation update
   
   ## Testing
   - [ ] Tests added/updated
   - [ ] All tests pass
   - [ ] Manual testing completed
   
   ## Related Issues
   Fixes #(issue number)
   
   ## Screenshots (if applicable)
   
   ## Additional Notes
   Any additional information or context.
   ```

### Review Process

1. **Automated Checks**
   - CI tests must pass
   - Code coverage maintained
   - Security scans pass
   - Code quality checks pass

2. **Code Review**
   - At least one maintainer review
   - Address all feedback
   - Update PR as needed

3. **Final Steps**
   - Squash commits if requested
   - Update CHANGELOG.md if needed
   - Merge after approval

## 🐛 Bug Reports

### Before Reporting

1. **Search Existing Issues**: Check if the bug has already been reported
2. **Update Dependencies**: Ensure you're using the latest version
3. **Minimal Reproduction**: Create minimal code to reproduce the issue

### Bug Report Template

```markdown
**Describe the bug**
A clear description of what the bug is.

**To Reproduce**
Steps to reproduce the behavior:
1. Go to '...'
2. Click on '....'
3. Scroll down to '....'
4. See error

**Expected behavior**
What you expected to happen.

**Screenshots**
If applicable, add screenshots.

**Environment:**
- OS: [e.g. Ubuntu 20.04]
- PHP Version: [e.g. 8.3]
- Laravel Version: [e.g. 12.0]
- Package Version: [e.g. 1.0.0]

**Additional context**
Add any other context about the problem here.
```

## 💡 Feature Requests

### Before Requesting

1. **Check Existing Requests**: Look for similar feature requests
2. **Consider Scope**: Ensure the feature fits the project's goals
3. **Provide Use Cases**: Explain why the feature would be valuable

### Feature Request Template

```markdown
**Is your feature request related to a problem?**
A clear description of what the problem is.

**Describe the solution you'd like**
A clear description of what you want to happen.

**Describe alternatives you've considered**
Alternative solutions or features you've considered.

**Use cases**
Specific use cases where this feature would be helpful.

**Additional context**
Any other context or screenshots about the feature request.
```

## 📚 Documentation Contributions

### Documentation Types

- **API Documentation**: Update API reference docs
- **Guides**: Step-by-step tutorials and guides
- **Examples**: Code examples and sample implementations
- **FAQ**: Common questions and answers
- **Troubleshooting**: Problem-solving guides

### Documentation Guidelines

- **Clear and Concise**: Write clear, easy-to-understand content
- **Code Examples**: Include working code examples
- **Screenshots**: Add screenshots for UI-related documentation
- **Testing**: Test all code examples before submitting
- **Formatting**: Use proper Markdown formatting

## 🛡️ Security

### Reporting Security Issues

**DO NOT** report security vulnerabilities through public GitHub issues.

Instead, please report them to: **security@oauth-provider.com**

Include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

We will respond within 48 hours and keep you updated on our progress.

### Security Guidelines

- **Never commit secrets**: Use environment variables
- **Follow OWASP guidelines**: Apply security best practices
- **Input validation**: Validate all user inputs
- **Output encoding**: Properly encode outputs
- **Authentication**: Use secure authentication methods
- **Authorization**: Implement proper access controls

## 👥 Community

### Code of Conduct

Please read and follow our [Code of Conduct](CODE_OF_CONDUCT.md).

### Communication Channels

- **GitHub Issues**: Bug reports and feature requests
- **GitHub Discussions**: General questions and discussions
- **Discord**: Real-time chat (coming soon)
- **Email**: support@oauth-provider.com

### Getting Help

1. **Documentation**: Check the comprehensive documentation
2. **FAQ**: Look at frequently asked questions
3. **GitHub Discussions**: Ask questions in discussions
4. **Issues**: Create an issue for bugs or feature requests

## 🎯 Development Priorities

### High Priority

- Security improvements
- Performance optimizations
- Bug fixes
- Documentation improvements

### Medium Priority

- New OAuth2/OIDC features
- Developer experience improvements
- Testing enhancements
- Integration examples

### Low Priority

- UI/UX improvements
- Non-critical features
- Code refactoring
- Developer tools

## 📈 Release Process

### Version Numbering

We follow [Semantic Versioning](https://semver.org/):

- **MAJOR**: Incompatible API changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes (backward compatible)

### Release Schedule

- **Major releases**: Quarterly
- **Minor releases**: Monthly
- **Patch releases**: As needed for critical fixes

### Release Checklist

- [ ] All tests pass
- [ ] Documentation updated
- [ ] CHANGELOG.md updated
- [ ] Version numbers updated
- [ ] Security review completed
- [ ] Performance benchmarks run

## 🏆 Recognition

Contributors will be recognized in:

- **CONTRIBUTORS.md**: List of all contributors
- **Release notes**: Major contributions highlighted
- **GitHub**: Contributor graphs and statistics
- **Documentation**: Author credits where appropriate

## ❓ Questions?

If you have questions about contributing:

1. Check the [FAQ](docs/faq.md)
2. Look at [GitHub Discussions](https://github.com/your-username/laravel-oauth-provider/discussions)
3. Open a new discussion or issue
4. Email us at support@oauth-provider.com

Thank you for contributing to Laravel OAuth2/OIDC Identity Provider! 🎉