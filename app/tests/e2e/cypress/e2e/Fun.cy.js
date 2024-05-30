describe('Fun', () => {
  beforeEach(() => {
    cy.intercept({ method: 'GET', pathname: /^\/api\/packages\/[\w-]+$/ }).as('api-packages')
    cy.visit('/fun')
  })

  it('shows title', () => {
    cy.contains('h1', 'Fun statistics')
  })

  it('shows categories', () => {
    cy.contains('a', 'Browsers')
  })

  it('navigates to fun detail page and shows charts', () => {
    cy.get('[data-test=Browsers]').within(() => {
      cy.get('a').click()
    })
    cy.wait('@api-packages')
    cy.contains('h1', 'Browsers statistics')
    cy.get('[data-test=packages-bar-chart]').should('have.length', 1)
    cy.get('[data-test=graph-chart-link]').click()
    cy.wait('@api-packages')
    cy.assertCanvasIsNotEmpty('[data-test=package-chart][data-test-rendered=true][style]')
  })
})
