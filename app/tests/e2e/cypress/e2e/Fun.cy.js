import FunConfig from '../../../../src/config/fun.json'

describe('Fun', () => {
  beforeEach(() => {
    cy.intercept({ method: 'GET', pathname: /^\/api\/packages\/[\w-]+$/ }).as('api-packages')
    cy.visit('/fun')
    cy.wait('@api-packages')
  })

  it('shows title', () => {
    cy.contains('h1', 'Fun statistics')
  })

  it('shows categories', () => {
    cy.contains('a', 'Browsers')
  })

  it('shows packages', () => {
    cy.get('[data-test-name=firefox] [role=progressbar][aria-valuenow*="."]').invoke('text').should('match', /^\d+/)
    cy.contains('a', 'firefox')
  })

  it('scrolls down and loads lazy', () => {
    const entries = Object.entries(FunConfig).flat(2)
    const entriesLength = entries.length
    const lastPackage = entries.at(-1)
    const packageRowHeight = 30

    cy.scrollTo(0, packageRowHeight * entriesLength)
    cy.wait('@api-packages')

    cy.get(`[data-test-name=${lastPackage}] [role=progressbar][aria-valuenow*="."]`, { timeout: 30000 }).invoke('text').should('match', /^\d+/)
    cy.contains('a', lastPackage)
  })
})
