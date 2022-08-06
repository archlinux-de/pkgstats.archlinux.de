describe('Packages', () => {
  beforeEach(() => {
    cy.intercept({ method: 'GET', pathname: /^\/api\/packages$/ }).as('api-packages-query')
  })

  it('shows title', () => {
    cy.visit('/packages')
    cy.wait('@api-packages-query')

    cy.contains('h1', 'Package statistics')
  })

  it('searches', () => {
    cy.visit('/packages')
    cy.wait('@api-packages-query')

    cy.get('input[type=search]').type('firefox')
    cy.location().should((loc) => {
      expect(loc.search).to.eq('?query=firefox')
    })
    cy.get('[data-test-name=firefox] [role=progressbar][aria-valuenow*="."]').invoke('text').should('match', /^\d+/)
    cy.contains('[role=alert]', 'packages found')
    cy.contains('a', 'firefox')
  })

  it('handles invalid input', () => {
    cy.visit('/packages')
    cy.wait('@api-packages-query')

    cy.get('input[type=search]').type('{bar}', { parseSpecialCharSequences: false })
    cy.contains('[role=alert]', 'Bad Request')
  })

  it('loads additional packages when scrolling', () => {
    cy.visit('/packages')
    cy.wait('@api-packages-query')

    const packageLimit = 60
    const packageRowHeight = 30

    cy.get('table').find('tr').should('have.length', packageLimit + 1)
    cy.scrollTo(0, packageRowHeight * packageLimit)
    cy.wait('@api-packages-query')

    cy.get('table').find('tr').should('have.length', packageLimit * 2 + 1)
  })

  it('searches by url', () => {
    cy.visit('/packages?query=firefox')
    cy.wait('@api-packages-query')

    cy.get('[role=progressbar]').should('be.visible')
    cy.contains('[role=alert]', 'packages found')
    cy.contains('a', 'firefox')
  })
})
