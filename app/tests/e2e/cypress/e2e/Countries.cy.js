describe('Countries', () => {
  beforeEach(() => {
    cy.intercept({ method: 'GET', pathname: /^\/api\/countries$/ }).as('api-countries')
    cy.visit('/countries')
    cy.wait('@api-countries')
  })

  it('shows title', () => {
    cy.contains('h1', 'Countries')
  })

  it('shows map', () => {
    cy.get('#countries-map .svgMap-map-image').should('be.visible')
  })
})
