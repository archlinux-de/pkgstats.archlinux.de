describe('Package', () => {
  beforeEach(() => {
    cy.visit('/packages/firefox')
  })

  it('shows title', () => {
    cy.contains('h1', 'firefox')
  })

  it('shows chart', () => {
    cy.assertCanvasIsNotEmpty('#package-chart[style]')
  })
})
