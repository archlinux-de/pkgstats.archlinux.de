describe('Package', () => {
  beforeEach(() => {
    cy.intercept({ method: 'GET', pathname: /^\/api\/packages\/[\w-]+\/series$/ }).as('api-packages-series')
    cy.visit('/packages/firefox')
    cy.wait('@api-packages-series')
  })

  it('shows title', () => {
    cy.contains('h1', 'firefox')
  })

  it('shows chart', () => {
    cy.assertCanvasIsNotEmpty('[data-test=package-chart][data-test-rendered=true][style]')
  })
})
