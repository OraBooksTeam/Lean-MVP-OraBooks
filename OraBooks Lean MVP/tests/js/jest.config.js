module.exports = {
  testEnvironment: 'jsdom',
  setupFiles: [require.resolve('./jest.setup.js')],
  testMatch: ['<rootDir>/*.test.js'],
  moduleDirectories: ['node_modules'],
  verbose: true
};
